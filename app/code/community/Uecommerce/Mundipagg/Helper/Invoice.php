<?php

class Uecommerce_Mundipagg_Helper_Invoice extends Mage_Core_Helper_Abstract
{


    public function create(Mage_Sales_Model_Order $order, $amount)
    {
        try {
            $log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

            if(!$order->canInvoice())
            {
                $log->error('Cannot create invoice');
                Mage::throwException('Cannot create invoice');
            }

            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

            if (!$invoice->getTotalQty()) {
                $log->error('Cannot create invoice');
                Mage::throwException('Cannot create invoice');
            }

            //Clear paid amount to prevent duplicated values
            $order->setTotalPaid(0);

            $invoice->register();
            $invoice->setBaseGrandTotal($amount);
            $invoice->setGrandTotal($amount);
            $invoice->setRequestedCaptureCase('online')->setCanVoidFlag(false)->pay();

            if (Mage::helper('sales')->canSendNewInvoiceEmail($order->getStoreId())) {
                $invoice->sendEmail(true);
                $invoice->setEmailSent(true);
            }

            Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

            $order->save();

            return true;

        } catch (Mage_Core_Exception $e) {
            return $e->getMessage();
        }
    }
}