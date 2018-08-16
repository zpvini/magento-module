<?php

class Uecommerce_Mundipagg_Helper_TwoCreditCardsPostNotificationHandler extends Mage_Core_Helper_Abstract
{
    private $log;
    private $notificationPostData;
    private $orderReference;
    private $transactionKey;
    private $creditCardTransactionStatus;
    private $capturedAmountInCents;
    private $mundipaggOrderStatus;

    const TRANSACTION_NOT_FOUND_ON_MAGENTO  = 'Transaction not found on Magento transactions: ';
    const TRANSACTION_NOT_FOUND_ON_ADDITIONAL_INFO  = 'Transaction not found on additional information: ';
    const TRANSACTION_ALREADY_UPDATED = 'OK - Transaction already updated with status: ';
    const TRANSACTION_UPDATED = 'MP - Two credit cards transaction update received: ';
    const CURRENT_ORDER_STATE = 'Current order state: ';
    const CURRENT_ORDER_STATUS = 'Current order status: ';
    const ORDER_HISTORY_ADD = 'Order history add: ';

    /**
     * @return mixed
     */
    public function getNotificationPostJson()
    {
        return $this->notificationPostData;
    }

    /**
     * @param mixed $notificationPostJson
     */
    public function setNotificationPostData($notificationPostData)
    {
        $this->notificationPostData = $notificationPostData;
    }

    /**
     * @return mixed
     */
    public function getOrderReference()
    {
        return $this->orderReference;
    }

    /**
     * @param mixed $orderReference
     */
    public function setOrderReference($orderReference)
    {
        $this->orderReference = $orderReference;
    }

    /**
     * @return mixed
     */
    public function getTransactionKey()
    {
        return $this->transactionKey;
    }

    /**
     * @param mixed $transactionKey
     */
    public function setTransactionKey($transactionKey)
    {
        $this->transactionKey = $transactionKey;
    }

    /**
     * @return mixed
     */
    public function getCreditCardTransactionStatus()
    {
        return $this->creditCardTransactionStatus;
    }

    /**
     * @param mixed $creditCardTransactionStatus
     */
    public function setCreditCardTransactionStatus($creditCardTransactionStatus)
    {
        $this->creditCardTransactionStatus = $creditCardTransactionStatus;
    }

    /**
     * @return mixed
     */
    public function getCapturedAmountInCents()
    {
        return $this->capturedAmountInCents;
    }

    /**
     * @param mixed $capturedAmountInCents
     */
    public function setCapturedAmountInCents($capturedAmountInCents)
    {
        $this->capturedAmountInCents = $capturedAmountInCents;
    }

    /**
     * @return mixed
     */
    public function getMundipaggOrderStatus()
    {
        return $this->mundipaggOrderStatus;
    }

    /**
     * @param mixed $mundipaggOrderStatus
     */
    public function setMundipaggOrderStatus($mundipaggOrderStatus)
    {
        $this->mundipaggOrderStatus = $mundipaggOrderStatus;
    }

    private function splitNotificationPostData($data)
    {
        $this->setNotificationPostData($data);
        $this->setCapturedAmountInCents($data['CreditCardTransaction']['CapturedAmountInCents']);
        $this->setCreditCardTransactionStatus($data['CreditCardTransaction']['CreditCardTransactionStatus']);
        $this->setOrderReference($data['OrderReference']);
        $this->setTransactionKey($data['CreditCardTransaction']['TransactionKey']);
        $this->setMundipaggOrderStatus($data['OrderStatus']);
    }

    private function setLogHeader()
    {
        $this->log->setLogLabel("Order #{$this->getOrderReference()}");
        $this->log->info("Processing two credit cards order " );

        $info['Transaction key'] = $this->getTransactionKey();
        $info['CreditCardTransactionStatus: '] = $this->getCreditCardTransactionStatus();
        $info['Mundipagg OrderStatus'] = $this->getMundipaggOrderStatus();

        $this->log->info(json_encode($info, JSON_PRETTY_PRINT));
    }

    public function processTwoCreditCardsNotificationPost(Mage_Sales_Model_Order $order, $notificationPostData)
    {
        $this->log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

        try {
            $this->splitNotificationPostData($notificationPostData);
            $this->setLogHeader();

            $this->log->info(self::CURRENT_ORDER_STATE . $order->getState() .' => ' . $order->getStatus());

            $transactionHelper = Mage::helper('mundipagg/transaction');
            $transaction = $transactionHelper->getTransaction($order->getEntityId(), $this->getTransactionKey());

            $additionalInformation = $this->getAdditionalInformation($order);
            $cardPrefix = $this->discoverCardPrefix($additionalInformation);


            if (empty($transaction->getTransactionId())) {
                Mage::throwException(SELF::TRANSACTION_NOT_FOUND_ON_MAGENTO . $this->getTransactionKey());
            }

            $totalPaid = $order->getTotalPaid();

            $this->addOrderHistoryStatusUpdate($order, $cardPrefix);
            $order->save();





            //$payment->setAdditionalInformation('CreditCardTransactionStatusEnum');

            // Atualizar o total pago

            // Se o status do pedido na mundi estiver como paid
            // Atualizar histórico
            // Colocar como processing

            // Se o status do pedido na mundi estiver como open
            // Atualizar histórico AuthorizedPendingCapture e deixar como pendente mesmo

            //Se tiver não autorizado, cancelar o pedido





            return;

        } catch (Exception $e) {
            $this->log->error($e->getMessage());
            return $e->getMessage();
        }
    }

    private function getAdditionalInformation($order)
    {
        $payment = $order->getPayment();
        return $payment->getAdditionalInformation();
    }

    /**
     * Discover card sort order by TransactionKey in
     * additional information.
     * @param array $additionalInformation
     * @return string 1_ or 2_ for 2 credit cards
     * @throws Mage_Core_Exception
     */
    private function discoverCardPrefix($additionalInformation)
    {
        $transactionKey = $this->getTransactionKey();

        if (
            !empty($additionalInformation['1_TransactionKey']) &&
            $additionalInformation['1_TransactionKey'] == $transactionKey
        ) {
            return '1_';
        }

        if (
            !empty($additionalInformation['2_TransactionKey']) &&
            $additionalInformation['2_TransactionKey'] == $transactionKey
        ) {
            return '2_';
        }

        $util = Mage::helper('mundipagg/util');

        Mage::throwException(SELF::TRANSACTION_NOT_FOUND_ON_ADDITIONAL_INFO . $this->getTransactionKey() . "\n" .
            "Additional information: \n\n" .
            $util->arrayToString($additionalInformation)
        );
    }
    private function addOrderHistoryStatusUpdate($order, $cardPrefix)
    {
        $comment =
            self::TRANSACTION_UPDATED .
            $this->getCreditCardTransactionStatus() . '<br>' .
            self::CURRENT_ORDER_STATE . $order->getState() .' => ' . $order->getStatus() . '<br>' .
            'Transacion key: ' . $this->getTransactionKey() . '<br>' .
            'Card sort order: ' . str_replace('_', '', $cardPrefix)
        ;

        $order->addStatusHistoryComment($comment, false);
        $this->log->info(self::ORDER_HISTORY_ADD . $comment);
    }
    private function alreadyUpdated($additionalInformation, $cardPrefix)
    {
        return
            $additionalInformation[$cardPrefix . 'CreditCardTransactionStatus'] ==
            $this->getCreditCardTransactionStatus();
    }
}