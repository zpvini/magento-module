<?php

class Uecommerce_Mundipagg_Helper_Transaction extends Mage_Core_Helper_Abstract
{
    const TRANSACTION_CAPTURED         = "Transaction captured";
    const TRANSACTION_ALREADY_CAPTURED = "Transaction already captured";
    const TRANSACTION_NOT_FOUND        = "Transaction not found";
    const ORDER_OVERPAID               = "Order overpaid";
    const ORDER_UNDERPAID              = "Order underpaid";
    const UNEXPECTED_ERROR             = "Unexpected error";

    protected function roundMagentoFloatNumber($number)
    {
        $parts = explode(".",$number);
        $decimal = isset($parts[1]) ? substr($parts[1],0,2) : '';
        return floatval($parts[0] . '.' . $decimal);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param $amountToCapture
     * @param $transactionKey
     * @param $amountInCents
     * @return string
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function captureTransaction(
        Mage_Sales_Model_Order $order,
        $amountToCapture,
        $transactionKey,
        $amountInCents = null
    )
    {
        $log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $log->setLogLabel("#{$order->getIncrementId()} | {$transactionKey}");
        $totalPaid = $this->roundMagentoFloatNumber($order->getTotalPaid());
        $grandTotal = $this->roundMagentoFloatNumber($order->getGrandTotal());

        if ($amountInCents !== null) {
            $amount = $this->roundMagentoFloatNumber($amountInCents * 0.01);
            if ($grandTotal != $amount) {
                $log->warning('Grand Total differs from amount in Mundipagg: ' . $grandTotal . ' != ' . $amount);
                $grandTotal = $amount;
            }
        }

        $orderPayment = new Uecommerce_Mundipagg_Model_Order_Payment();
        if (is_null($totalPaid)) {
            $totalPaid = 0;
        }
        $totalPaid += $this->roundMagentoFloatNumber($amountToCapture);

        $entityId = $order->getEntityId();

        $this->validateTransactions($entityId, $transactionKey);
        $transaction = $this->getTransaction($entityId,$transactionKey);

        $order->setBaseTotalPaid($totalPaid)
            ->setTotalPaid($totalPaid)
            ->save();
        $accTotalPaid = $this->roundMagentoFloatNumber($totalPaid);
        $accGrandTotal = $this->roundMagentoFloatNumber($grandTotal);
        switch (true) {

            // total paid equal grand_total, create invoice
            case $accTotalPaid == $accGrandTotal:
                try {
                    $standard = new Uecommerce_Mundipagg_Model_Standard();
                    $invoice = $orderPayment->orderPaid($order, $standard);
                    return $invoice;
                } catch (Exception $e) {
                    Mage::throwException($e->getMessage());
                }
                break;
            // order overpaid
            case $accTotalPaid > $accGrandTotal:
                try {
                    $orderPayment->orderOverpaid($order);
                } catch (Exception $e) {
                    Mage::throwException("Cannot set order to overpaid: {$e->getMessage()}");
                }
                return self::ORDER_OVERPAID;
                break;
            // order underpaid
            case $accTotalPaid < $accGrandTotal:
                try {
                    $orderPayment->orderUnderPaid($order);
                } catch (Exception $e) {
                    Mage::throwException("Cannot set order to underpaid: {$e->getMessage()}");
                }
                $transaction->setOrderPaymentObject($order->getPayment());
                $transaction->setIsClosed(true)->save();
                if ($order->getPayment()->getMethod() === 'mundipagg_twocreditcards') {
                    return self::TRANSACTION_CAPTURED;
                } else {
                    return self::ORDER_UNDERPAID;
                }
                break;
            // unexpected situation
            default:
                Mage::throwException(self::UNEXPECTED_ERROR);
                break;
        }
    }

    /**
     * Throw an exception if the transaction not found.
     * @param $entityId
     * @param $transactionKey
     * @throws Mage_Core_Exception
     */
    public function validateTransactions($entityId, $transactionKey)
    {
        $transactionAuthorization = $this->getTransaction($entityId, $transactionKey . "-authorization");
        $transactionOrder = $this->getTransaction($entityId, $transactionKey . "-order");

        if (is_null($transactionAuthorization) && is_null($transactionOrder)) {
            Mage::throwException(self::TRANSACTION_NOT_FOUND);
        } else if (count($transactionAuthorization) > 1 || count($transactionOrder) > 1) {
            Mage::throwException("More than one transaction for the TransactionKey in the database");
        }

        if ($transactionAuthorization->getIsClosed() || $transactionOrder->getIsClosed()) {
            Mage::throwException(self::TRANSACTION_ALREADY_CAPTURED);
        }
        return;
    }

    public function getTransaction($entityId, $transactionKeyString)
    {
        /** @var Mage_Sales_Model_Resource_Order_Payment_Transaction_Collection $transactions */
        $transactions = Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addAttributeToFilter('order_id', ['eq' => $entityId])
            ->addAttributeToFilter('txn_id', ['like' => $transactionKeyString . "%"]);

        return $transactions->getFirstItem();
    }
}
