<?php

class Uecommerce_Mundipagg_Model_Order_Payment {

	const ERR_CANNOT_CREATE_INVOICE                  = "Cannot create invoice";
	const ERR_CANNOT_CREATE_INVOICE_WITHOUT_PRODUCTS = "Cannot create invoice without products";
	const ERR_UNEXPECTED_ERROR                       = "Unexpected error";

	/**
	 * @param Mage_Sales_Model_Order $order
	 * @throws Exception
	 * @return Mage_Sales_Model_Order_Invoice
	 */
	public function createInvoice(Mage_Sales_Model_Order $order) {

		if (!$order->canInvoice()) {
			Mage::throwException(self::ERR_CANNOT_CREATE_INVOICE);
		}

		$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

		if (!$invoice->getTotalQty()) {
			Mage::throwException(self::ERR_CANNOT_CREATE_INVOICE_WITHOUT_PRODUCTS);
		}

		$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
		$invoice->register();
		$invoice->getOrder()->setCustomerNoteNotify(true);
		$invoice->getOrder()->setIsInProcess(true);
		$invoice->setCanVoidFlag(true);
		$invoice->pay();

		try {
			$transactionSave = Mage::getModel('core/resource_transaction')
				->addObject($invoice)
				->addObject($invoice->getOrder());

			$transactionSave->save();

		} catch (Exception $e) {
			Mage::throwException($e->getMessage());
		}

		$log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
		$log->info("#{$order->getIncrementId()} | invoice created {$invoice->getIncrementId()}");

		return $invoice;
	}

	public function orderOverpaid(Mage_Sales_Model_Order $order) {
		try {
			$order->setStatus('overpaid')
				->save();
		} catch (Exception $e) {
			Mage::throwException($e);
		}
	}

	public function orderUnderPaid(Mage_Sales_Model_Order $order, $amountToPaid = null) {
		try {
			$order->setStatus('underpaid');

			if (is_null($amountToPaid) === false) {
				$order->setBaseTotalPaid($amountToPaid)
					->setTotalPaid($amountToPaid);
			}

			$order->save();

		} catch (Exception $e) {
			Mage::throwException($e);
		}
	}

}