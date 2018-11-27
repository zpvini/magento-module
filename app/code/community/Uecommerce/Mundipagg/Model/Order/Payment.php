<?php

class Uecommerce_Mundipagg_Model_Order_Payment
{

    const ERR_CANNOT_CREATE_INVOICE                  = "Cannot create invoice";
    const ERR_CANNOT_CREATE_INVOICE_WITHOUT_PRODUCTS = "Cannot create invoice without products";
    const ERR_UNEXPECTED_ERROR                       = "Unexpected error";

    /**
     * @param Mage_Sales_Model_Order $order
     * @throws Exception
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function createInvoice(Mage_Sales_Model_Order $order)
    {
        if (!$order->canInvoice()) {
            Mage::throwException(self::ERR_CANNOT_CREATE_INVOICE);
        }

        // reset total paid because invoice generation set order total_paid also
        $order->setBaseTotalPaid(null)
            ->setTotalPaid(null)
            ->save();

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

    /**
     * @param Mage_Sales_Model_Order              $order
     * @param Uecommerce_Mundipagg_Model_Standard $standard
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function orderPaid(Mage_Sales_Model_Order $order, Uecommerce_Mundipagg_Model_Standard $standard)
    {
        try {
            $invoice = $this->createInvoice($order, $standard);

            $log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
            $log->setLogLabel("#{$order->getIncrementId()}");

            $standard->closeAuthorizationTxns($order);
            $log->info("Authorization transactions closed");

            if ($order->getTotalPaid() < $invoice->getBaseGrandTotal()) {

                $log->info("Order Total Paid (" . intval($order->getTotalPaid()) .
                    ") is less than Invoice Total (" . $invoice->getBaseGrandTotal() . ")");

                $order
                    ->setBaseTotalPaid($invoice->getBaseGrandTotal())
                    ->setTotalPaid($invoice->getBaseGrandTotal())
                    ->save();

                $log->info("Order Total Paid updated: " . $order->getTotalPaid());
            }

            return $invoice;
        } catch (Exception $e) {
            Mage::throwException($e);
        }
    }

    public function orderOverpaid(Mage_Sales_Model_Order $order)
    {
        try {
            $order->setStatus('overpaid')
                ->save();
        } catch (Exception $e) {
            Mage::throwException($e);
        }
    }

    public function orderUnderPaid(Mage_Sales_Model_Order $order, $amountToPaid = null)
    {
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
