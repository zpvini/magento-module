<?php

/**
 * Class Uecommerce_Mundipagg_Helper_ProcessOrderStatus
 * Deal with order status.
 */
class Uecommerce_Mundipagg_Helper_ProcessOrderStatus extends Mage_Core_Helper_Abstract
{
    const TRANSACTION_CAPTURED = "Transaction captured";

    public function captured($order, $amountToCapture, $transactionKey, $orderReference)
    {
        $log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $transactionHelper = Mage::helper('mundipagg/transaction');
        
        try {
            $return = $transactionHelper->captureTransaction($order, $amountToCapture, $transactionKey);
        } catch (Exception $e) {
            $errMsg = $e->getMessage();
            $returnMessage = "OK | #{$orderReference} | {$transactionKey} | ";
            $returnMessage .= "Can't capture transaction: {$errMsg}";
            $log->info($returnMessage);
            $log->info("Current order status: " . $order->getStatusLabel());
            return $returnMessage;
        }
        if ($return instanceof Mage_Sales_Model_Order_Invoice) {
            Mage::helper('mundipagg')->sendNewInvoiceEmail($return,$order);

            $returnMessage = "OK | #{$orderReference} | {$transactionKey} | " . self::TRANSACTION_CAPTURED;
            $log->info($returnMessage);
            $log->info("Current order status: " . $order->getStatusLabel());
            return $returnMessage;
        }
        if ($return === self::TRANSACTION_CAPTURED) {
            $returnMessage = "OK | #{$orderReference} | {$transactionKey} | Transaction captured.";
            $log->info($returnMessage);
            $log->info("Current order status: " . $order->getStatusLabel());
            return $returnMessage;
        }
        // cannot capture transaction
        $returnMessage = "KO | #{$orderReference} | {$transactionKey} | Transaction can't be captured: ";
        $returnMessage .= $return;
        $log->info($returnMessage);
        $log->info("Current order status: " . $order->getStatusLabel());
        return $returnMessage;
    }
}