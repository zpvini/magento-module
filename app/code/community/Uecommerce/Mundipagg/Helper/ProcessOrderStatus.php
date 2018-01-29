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

    public function paidOverpaid($api,$order,$returnMessageLabel,$capturedAmountInCents,$data,$status) {

        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

        if ($order->canUnhold()) {
            $order->unhold();
            $helperLog->info("{$returnMessageLabel} | unholded.");
            $helperLog->info("Current order status: " . $order->getStatusLabel());
        }
        if (!$order->canInvoice()) {
            $returnMessage = "OK | {$returnMessageLabel} | Can't create invoice. Transaction status '{$status}' processed.";
            $helperLog->info($returnMessage);
            $helperLog->info("Current order status: " . $order->getStatusLabel());
            return $returnMessage;
        }
        // Partial invoice
        $epsilon = 0.00001;
        if ($order->canInvoice() && abs($order->getGrandTotal() - $capturedAmountInCents * 0.01) > $epsilon) {
            $baseTotalPaid = $order->getTotalPaid();
            // If there is already a positive baseTotalPaid value it's not the first transaction
            if ($baseTotalPaid > 0) {
                $baseTotalPaid += $capturedAmountInCents * 0.01;
                $order->setTotalPaid(0);
            } else {
                $baseTotalPaid = $capturedAmountInCents * 0.01;
                $order->setTotalPaid($baseTotalPaid);
            }
            $accOrderGrandTotal = sprintf($order->getGrandTotal());
            $accBaseTotalPaid = sprintf($baseTotalPaid);
            // Can invoice only if total captured amount is equal to GrandTotal
            if ($accBaseTotalPaid == $accOrderGrandTotal) {
                $result = $api->createInvoice($order, $data, $baseTotalPaid, $status);
                return $result;
            } elseif ($accBaseTotalPaid > $accOrderGrandTotal) {
                $order->setTotalPaid(0);
                $result = $api->createInvoice($order, $data, $baseTotalPaid, $status);
                return $result;
            } else {
                $order->save();
                $returnMessage = "OK | {$returnMessageLabel} | ";
                $returnMessage .= "Captured amount isn't equal to grand total, invoice not created.";
                $returnMessage .= "Transaction status '{$status}' received.";
                $helperLog->info($returnMessage);
                $helperLog->info("Current order status: " . $order->getStatusLabel());
                return $returnMessage;
            }
        }
        // Create invoice
        if ($order->canInvoice() && abs($capturedAmountInCents * 0.01 - $order->getGrandTotal()) < $epsilon) {
            $result = $api->createInvoice($order, $data, $order->getGrandTotal(), $status);
            return $result;
        }
        $returnMessage = "Order {$order->getIncrementId()} | Unable to create invoice for this order.";
        $helperLog->error($returnMessage);
        $helperLog->info("Current order status: " . $order->getStatusLabel());
        return "KO | {$returnMessage}";
    }
}