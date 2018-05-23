<?php

class Uecommerce_Mundipagg_Model_Api extends Uecommerce_Mundipagg_Model_Standard
{
    const INTEGRATION_TIMEOUT = "MundiPagg API timeout, waiting notification";

    private $helperUtil;
    private $modelStandard;
    private $debugEnabled;
    private $moduleVersion;

    public function __construct($Store = null)
    {
        $this->helperUtil = new Uecommerce_Mundipagg_Helper_Util();
        $this->modelStandard = new Uecommerce_Mundipagg_Model_Standard($Store);
        $this->moduleVersion = Mage::helper('mundipagg')->getExtensionVersion();
        $this->debugEnabled = $this->modelStandard->getDebug();
        parent::_construct();
    }

    public function getHolderNameByInstantBuyKey($instantBuyKey)
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            "MerchantKey: {$this->modelStandard->getMerchantKey()}"
        ];

        try {
            $url = $this->getInstantBuyKeyUrl($instantBuyKey);

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $responseRaw = curl_exec($ch);

            // Close connection
            curl_close($ch);

            $responseData = json_decode($responseRaw, true);
            $holderName = $responseData['CreditCardDataCollection'][0]['HolderName'];

            if ($holderName) {
                return $holderName;
            }

            Mage::throwException("Unable to get current credit card holder name by instant buy key");
        } catch (Exception $e) {
            $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
            $helperLog->error($e->getMessage(), true);

            return 'Failed';
        }
    }

    private function getInstantBuyKeyUrl($instantBuyKey)
    {
        switch ($this->getEnvironment()) {
            case 'localhost':
            case 'development':
            case 'staging':
            default:
                $environment = 'Staging';
                break;
            case 'production':
                $environment = 'Production';
                break;
        }

        $storeId = Mage::app()->getStore()->getStoreId();

        // all this extra code because we store the url with the endpoint hardcoded :(
        $url = trim($this->getConfigData('apiUrl' . $environment, $storeId));
        $url = rtrim($url, '/');
        $pos = strrpos($url, '/');

        return substr($url, 0, $pos) . "/CreditCard/{$instantBuyKey}";
    }

    /**
     * Credit Card Transaction
     */
    public function creditCardTransaction($order, $data, Uecommerce_Mundipagg_Model_Standard $standard)
    {
        $helper = Mage::helper('mundipagg');
        //Log error
        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $helperLog->setLogLabel('|' . $order->getIncrementId() . '|');

        try {
            $helperLog->info('creditCardTransaction()');

            // Set Data
            $_request = [];
            $_request["Order"] = [];
            $_request["Order"]["OrderReference"] = $order->getIncrementId();

            /*
            * Append transaction (multi credit card payments)
            * When one of Credit Cards has not been authorized and we try with a new one)
            */
            $orderReference = $order->getPayment()->getAdditionalInformation('OrderReference');
            if ($orderReference) {
                $_request["Order"]["OrderReference"] = $orderReference;
            }

            // Collection
            $_request["CreditCardTransactionCollection"] = [];

            /* @var $recurrencyModel Uecommerce_Mundipagg_Model_Recurrency */
            $recurrencyModel = Mage::getModel('mundipagg/recurrency');
            $creditcardTransactionCollection = [];

            // Partial Payment (we use this reference in order to authorize the rest of the amount)
            if ($order->getPayment()->getAdditionalInformation('OrderReference')) {
                $_request["CreditCardTransactionCollection"]["OrderReference"] = $order->getPayment()->getAdditionalInformation('OrderReference');
            }

            $baseGrandTotal = str_replace(',', '.', $order->getBaseGrandTotal());
            $amountInCentsVar = intval(strval(($baseGrandTotal * 100)));
            $num = $helper->getCreditCardsNumber($data['payment_method']);
            $installmentCount = 1;

            $approvalRequest = Mage::getSingleton('checkout/session')->getApprovalRequestSuccess();
            $creditCardOperationEnum = $this->getCreditCardOperation($approvalRequest, $standard);

            foreach ($data['payment'] as $i => $paymentData) {
                $creditcardTransactionData = new stdclass();
                $creditcardTransactionData->CreditCard = new stdclass();
                $creditcardTransactionData->Options = new stdclass();
                // InstantBuyKey payment
                if (isset($paymentData['card_on_file_id'])) {
                    $token = Mage::getModel('mundipagg/cardonfile')->load($paymentData['card_on_file_id']);
                    $helperLog->info('With InstantBuyKey');

                    if ($token->getId() && $token->getEntityId() == $order->getCustomerId()) {
                        $creditcardTransactionData->CreditCard->InstantBuyKey = $token->getToken();
                        $creditcardTransactionData->CreditCard->CreditCardBrand = $token->getCcType();
                        /** Tipo de operação: AuthOnly | AuthAndCapture | AuthAndCaptureWithDelay  */
                        $creditcardTransactionData->CreditCard->SecurityCode = $paymentData['cvv']; // Código de segurança
                        $creditcardTransactionData->CreditCardOperation = $creditCardOperationEnum;
                        $creditcardTransactionData->AmountInCents = intval(strval(($paymentData['AmountInCents']))); // Valor da transação
                        $creditcardTransactionData->InstallmentCount = $paymentData['InstallmentCount']; // Nº de parcelas
                        $creditcardTransactionData->Options->CurrencyIso = "BRL"; //Moeda do pedido
                    }
                } else { // Credit Card
                    $helperLog->info('Without InstantBuyKey');

                    $creditcardTransactionData->CreditCard->CreditCardNumber = $paymentData['CreditCardNumber']; // Número do cartão
                    $creditcardTransactionData->CreditCard->HolderName = $paymentData['HolderName']; // Nome do cartão
                    $creditcardTransactionData->CreditCard->SecurityCode = $paymentData['SecurityCode']; // Código de segurança
                    $creditcardTransactionData->CreditCard->ExpMonth = $paymentData['ExpMonth']; // Mês Exp
                    $creditcardTransactionData->CreditCard->ExpYear = $paymentData['ExpYear']; // Ano Exp
                    $creditcardTransactionData->CreditCard->CreditCardBrand = $paymentData['CreditCardBrandEnum']; // Bandeira do cartão : Visa ,MasterCard ,Hipercard ,Amex
                    $creditcardTransactionData->CreditCardOperation = $creditCardOperationEnum;
                    /** Tipo de operação: AuthOnly | AuthAndCapture | AuthAndCaptureWithDelay  **/
                    $creditcardTransactionData->AmountInCents = intval(strval(($paymentData['AmountInCents']))); // Valor da transação
                    $creditcardTransactionData->InstallmentCount = $paymentData['InstallmentCount']; // Nº de parcelas
                    $creditcardTransactionData->Options->CurrencyIso = "BRL"; //Moeda do pedido
                }

                $installmentCount = $paymentData['InstallmentCount'];

                // BillingAddress
                if ($standard->getAntiFraud() == 1) {
                    $addy = $this->buyerBillingData($order, $data, $_request, $standard);
                    $creditcardTransactionData->CreditCard->BillingAddress = $addy['AddressCollection'][0];
                }

                if ($standard->getEnvironment() != 'production') {
                    $helperLog->info('With Sandbox');

                    $creditcardTransactionData->Options->PaymentMethodCode = $standard->getPaymentMethodCode(); // Código do meio de pagamento
                }

                // Verificamos se tem o produto de teste da Cielo no carrinho
                foreach ($order->getItemsCollection() as $item) {
                    if ($item->getSku() == $standard->getCieloSku() && $standard->getEnvironment() == 'production') {
                        $creditcardTransactionData->Options->PaymentMethodCode = 5; // Código do meio de pagamento  Cielo
                    }
                    // Adicionamos o produto a lógica de recorrência.
                    $qty = $item->getQtyOrdered();
                    for ($qt = 1; $qt <= $qty; $qt++) {
                        $recurrencyModel->setItem($item);
                    }
                }

                $creditcardTransactionCollection[] = $creditcardTransactionData;
            }

            $_request["CreditCardTransactionCollection"] = $this->ConvertCreditcardTransactionCollectionFromRequest($creditcardTransactionCollection, $standard);
            if($data['payment_method'] === 'mundipagg_recurrencepayment') {
                $helperLog->info('With Recurrence payment');
                $_request = $recurrencyModel->generateRecurrences($_request, $installmentCount);
            }

            // Buyer data
            $_request["Buyer"] = [];
            $_request["Buyer"] = $this->buyerBillingData($order, $data, $_request, $standard);

            // Cart data
            $_request["ShoppingCartCollection"] = [];
            $_request["ShoppingCartCollection"] = $this->cartData($order, $data, $_request, $standard);

            //verify anti-fraud config and mount the node 'RequestData'
            $nodeRequestData = $this->getRequestDataNode();
            if (is_array($nodeRequestData)) {
                $_request['RequestData'] = $nodeRequestData;
            }

            // check anti fraud minimum value
            if ($helper->isAntiFraudEnabled()) {
                $antifraudProviderConfig = intval(Mage::getStoreConfig('payment/mundipagg_standard/antifraud_provider'));
                $antifraudProvider = null;

                switch ($antifraudProviderConfig) {
                    case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_CLEARSALE:
                        $antifraudProvider = 'clearsale';
                        break;
                    case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_FCONTROL:
                        $antifraudProvider = 'fcontrol';
                        break;
                    case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_STONE:
                        $antifraudProvider = 'stone';
                        break;
                }

                $minValueConfig = Mage::getStoreConfig("payment/mundipagg_standard/antifraud_minimum_{$antifraudProvider}");
                $minValueConfig = $helper->formatPriceToCents($minValueConfig);

                if ($amountInCentsVar >= $minValueConfig) {
                    $helperLog->info('With Antifraud enabled');
                    $helperLog->info('With Antifraud provider: ' . $antifraudProvider);

                    $_request['Options']['IsAntiFraudEnabled'] = true;
                } else {
                    $_request['Options']['IsAntiFraudEnabled'] = false;
                }
            }

            $response = $this->sendJSON($_request);

            $errorReport = $helper->issetOr($response['ErrorReport']);
            $orderKey = $helper->issetOr($response['OrderResult']['OrderKey']);
            $orderReference = $helper->issetOr($response['OrderResult']['OrderReference']);
            $createDate = $helper->issetOr($response['OrderResult']['CreateDate']);

            // if some error ocurred ex.: http 500 internal server error
            if (!is_null($errorReport)) {
                $helperLog->info('With Not null Error report');

                $errorItemCollection = $errorReport['ErrorItemCollection'];

                // Return errors
                return [
                    'error'               => 1,
                    'ErrorCode'           => $helper->issetOr($errorItemCollection[0]['ErrorCode']),
                    'ErrorDescription'    => $helper->issetOr($errorItemCollection[0]['Description']),
                    'OrderKey'            => $orderKey,
                    'OrderReference'      => $orderReference,
                    'ErrorItemCollection' => $errorItemCollection,
                    'result'              => $response,
                ];
            }

            // Transactions colllection
            $creditCardTransactionResultCollection = $helper->issetOr($response['CreditCardTransactionResultCollection']);
            $transactionsQty = count($creditCardTransactionResultCollection);

            // Only 1 transaction
            if (count($creditCardTransactionResultCollection) == 1) {
                $creditCardTransaction = $creditCardTransactionResultCollection[0];
                $success = $helper->issetOr($creditCardTransaction['Success'], false);

                $helperLog->info('With Only 1 transaction');

                //and transaction success is true
                if ($success === true) {
                    $helperLog->info('With Success true');

//					$trans = $creditCardTransactionResultCollection['CreditCardTransactionResult'];
                    // We save Card On File
                    if ($data['customer_id'] != 0 && isset($data['payment'][1]['token']) && $data['payment'][1]['token'] == 'new') {
                        $expiresAt = date(
                            "Y-m-t",
                            mktime(
                                0,
                                0,
                                0,
                                $data['payment'][1]['ExpMonth'],
                                1,
                                $data['payment'][1]['ExpYear']
                            )
                        );

                        $cardonfile = Mage::getModel('mundipagg/cardonfile');

                        $cardonfile->setEntityId($data['customer_id']);
                        $cardonfile->setAddressId($data['address_id']);
                        $cardonfile->setCcType($data['payment'][1]['CreditCardBrandEnum']);
                        $cardonfile->setCreditCardMask($creditCardTransaction['CreditCard']['MaskedCreditCardNumber']);
                        $cardonfile->setExpiresAt($expiresAt);
                        $cardonfile->setToken($creditCardTransaction['CreditCard']['InstantBuyKey']);
                        $cardonfile->setActive(1);
                        $cardonfile->save();
                    }

                    $result = [
                        'success'        => true,
                        'message'        => 1,
                        'returnMessage'  => urldecode($creditCardTransaction['AcquirerMessage']),
                        'OrderKey'       => $orderKey,
                        'OrderReference' => $orderReference,
                        'isRecurrency'   => $recurrencyModel->recurrencyExists(),
                        'result'         => $response
                    ];

                    if (is_null($createDate === false)) {
                        $result['CreateDate'] = $createDate;
                    }

                    return $result;
                } else {
                    // CreditCardTransactionResult success == false, not authorized
                    $helperLog->info('With Success false');

                    $result = [
                        'error'            => 1,
                        'ErrorCode'        => $creditCardTransaction['AcquirerReturnCode'],
                        'ErrorDescription' => urldecode($creditCardTransaction['AcquirerMessage']),
                        'OrderKey'         => $orderKey,
                        'OrderReference'   => $orderReference,
                        'result'           => $response
                    ];

                    return $result;
                }
            } elseif ($transactionsQty > 1) { // More than 1 transaction
                $helperLog->info('With more than 1 transactions');

                $transactionFailed = $this->ifOneOrMoreTransactionFailed($creditCardTransactionResultCollection);

                if ($transactionFailed) {
                    $helperLog->info('With Transaction failed');

                    $transactionFailed['OrderKey'] = $orderKey;
                    $transactionFailed['OrderReference'] = $orderReference;
                    $transactionFailed['result'] = $response;

                    return $transactionFailed;
                }

                $allTransactions = $creditCardTransactionResultCollection;

                // We remove other transactions made before
                $actualTransactions = count($data['payment']);
                $totalTransactions = count($allTransactions);
                $transactionsToDelete = $totalTransactions - $actualTransactions;

                if ($totalTransactions > $actualTransactions) {
                    for ($i = 0; $i <= ($transactionsToDelete - 1); $i++) {
                        unset($allTransactions[$i]);
                    }

                    // Reorganize array indexes from 0
                    $allTransactions = array_values($allTransactions);
                }

                foreach ($allTransactions as $key => $trans) {
                    // We save Cards On File for current transaction(s)
                    if ($data['customer_id'] != 0 && isset($data['payment'][$key + 1]['token']) && $data['payment'][$key + 1]['token'] == 'new') {
                        $cardonfile = Mage::getModel('mundipagg/cardonfile');
                        $cardonfile->setEntityId($data['customer_id']);
                        $cardonfile->setAddressId($data['address_id']);
                        $cardonfile->setCcType($data['payment'][$key + 1]['CreditCardBrandEnum']);
                        $cardonfile->setCreditCardMask($trans['CreditCard']['MaskedCreditCardNumber']);
                        $cardonfile->setExpiresAt(date("Y-m-t", mktime(0, 0, 0, $data['payment'][$key + 1]['ExpMonth'], 1, $data['payment'][$key + 1]['ExpYear'])));
                        $cardonfile->setToken($trans['CreditCard']['InstantBuyKey']);
                        $cardonfile->setActive(1);
                        $cardonfile->save();
                    }
                }

                // Result
                $result = [
                    'success'        => true,
                    'message'        => 1,
                    'OrderKey'       => $orderKey,
                    'OrderReference' => $orderReference,
                    'isRecurrency'   => $recurrencyModel->recurrencyExists(),
                    'result'         => $response,
                ];

                $createDate = $helper->issetOr($response['OrderResult']['CreateDate']);

                if (is_null($createDate) === false) {
                    $result['CreateDate'] = $createDate;
                }

                return $result;
            }
        } catch (Exception $e) {
            //Redirect to Cancel page
            Mage::getSingleton('checkout/session')->setApprovalRequestSuccess('cancel');

            $helperLog->error($e, true);

            //Mail error
            $this->mailError(print_r($e->getMessage(), 1));

            // Return error
            $approvalRequest['error'] = 'Error WS';
            $approvalRequest['ErrorCode'] = 'ErrorCode WS';
            $approvalRequest['ErrorDescription'] = 'ErrorDescription WS';
            $approvalRequest['OrderKey'] = '';
            $approvalRequest['OrderReference'] = '';

            return $approvalRequest;
        }

        $helperLog->info('With MundiPagg Timeout');
        // time out or no Mundipagg API response
        return false;
    }
    /**
     * Convert CreditcardTransaction Collection From Request
     */
    public function ConvertCreditcardTransactionCollectionFromRequest($creditcardTransactionCollectionRequest, $standard)
    {
        $newCreditcardTransCollection = array();
        $counter = 0;

        foreach ($creditcardTransactionCollectionRequest as $creditcardTransItem) {
            $creditcardTrans = array();
            $creditcardTrans["AmountInCents"] = $creditcardTransItem->AmountInCents;

            if (isset($creditcardTransItem->CreditCard->CreditCardNumber)) {
                $creditcardTrans['CreditCard']["CreditCardNumber"] = $creditcardTransItem->CreditCard->CreditCardNumber;
            }

            if (isset($creditcardTransItem->CreditCard->HolderName)) {
                $creditcardTrans['CreditCard']["HolderName"] = $creditcardTransItem->CreditCard->HolderName;
            }

            if (isset($creditcardTransItem->CreditCard->SecurityCode)) {
                $creditcardTrans['CreditCard']["SecurityCode"] = $creditcardTransItem->CreditCard->SecurityCode;
            }

            if (isset($creditcardTransItem->CreditCard->ExpMonth)) {
                $creditcardTrans['CreditCard']["ExpMonth"] = $creditcardTransItem->CreditCard->ExpMonth;
            }

            if (isset($creditcardTransItem->CreditCard->ExpYear)) {
                $creditcardTrans['CreditCard']["ExpYear"] = $creditcardTransItem->CreditCard->ExpYear;
            }

            if (isset($creditcardTransItem->CreditCard->InstantBuyKey)) {
                $creditcardTrans['CreditCard']["InstantBuyKey"] = $creditcardTransItem->CreditCard->InstantBuyKey;
            }

            $creditcardTrans['CreditCard']["CreditCardBrand"] = $creditcardTransItem->CreditCard->CreditCardBrand;
            $creditcardTrans["CreditCardOperation"] = $creditcardTransItem->CreditCardOperation;
            $creditcardTrans["InstallmentCount"] = $creditcardTransItem->InstallmentCount;
            $creditcardTrans['Options']["CurrencyIso"] = $creditcardTransItem->Options->CurrencyIso;

            if ($standard->getEnvironment() != 'production') {
                $creditcardTrans['Options']["PaymentMethodCode"] = $creditcardTransItem->Options->PaymentMethodCode;
            }

            if ($standard->getAntiFraud() == 1) {
                $creditcardTrans['CreditCard']['BillingAddress'] = $creditcardTransItem->CreditCard->BillingAddress;
                unset($creditcardTrans['CreditCard']['BillingAddress']['AddressType']);
            }

            $newCreditcardTransCollection[$counter] = $creditcardTrans;
            $counter += 1;
        }

        return $newCreditcardTransCollection;
    }
    /**
     * Boleto transaction
     **/
    public function boletoTransaction($order, $data, Uecommerce_Mundipagg_Model_Standard $standard)
    {
        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $helperLog->setLogLabel('|' . $order->getIncrementId() . '|');

        try {
            $helper = Mage::helper('mundipagg');
            $helperLog->info('boletoTransaction()');

            // Set Data
            $_request = [];
            $_request["Order"] = [];
            $_request["Order"]["OrderReference"] = $order->getIncrementId();
            $_request["BoletoTransactionCollection"] = [];

            $boletoTransactionCollection = new stdclass();

            for ($i = 1; $i <= $data['boleto_parcelamento']; $i++) {
                $boletoTransactionData = new stdclass();

                if (!empty($data['boleto_dates'])) {
                    $datePagamentoBoleto = $data['boleto_dates'][$i - 1];
                    $now = strtotime(date('Y-m-d'));
                    $yourDate = strtotime($datePagamentoBoleto);
                    $datediff = $yourDate - $now;
                    $daysToAddInBoletoExpirationDate = floor($datediff / (60 * 60 * 24));
                } else {
                    $daysToAddInBoletoExpirationDate = $standard->getDiasValidadeBoleto();
                }

                $baseGrandTotal = str_replace(',', '.', $order->getBaseGrandTotal());
                $amountInCentsVar = intval(strval((($baseGrandTotal / $data['boleto_parcelamento']) * 100)));
                $boletoTransactionData->AmountInCents = $amountInCentsVar;
                $boletoTransactionData->Instructions = $standard->getInstrucoesCaixa();

                if ($standard->getEnvironment() != 'production') {
                    $helperLog->info('With Sandbox');

                    $boletoTransactionData->BankNumber = $standard->getBankNumber();
                }

                $boletoTransactionData->DocumentNumber = '';
                $boletoTransactionData->Options = new stdclass();
                $boletoTransactionData->Options->CurrencyIso = 'BRL';
                $boletoTransactionData->Options->DaysToAddInBoletoExpirationDate = $daysToAddInBoletoExpirationDate;
                $addy = $this->buyerBillingData($order, $data, $_request, $standard);
                $boletoTransactionData->BillingAddress = $addy['AddressCollection'][0];
                $boletoTransactionCollection = array($boletoTransactionData);
            }

            $_request["BoletoTransactionCollection"] = $this->ConvertBoletoTransactionCollectionFromRequest($boletoTransactionCollection);

            // Buyer data
            $_request["Buyer"] = array();
            $_request["Buyer"] = $this->buyerBillingData($order, $data, $_request, $standard);

            // Cart data
            $_request["ShoppingCartCollection"] = array();
            $_request["ShoppingCartCollection"] = $this->cartData($order, $data, $_request, $standard);

            //verify anti-fraud config and mount the node 'RequestData'
            $nodeRequestData = $this->getRequestDataNode();
            if (is_array($nodeRequestData)) {
                $_request['RequestData'] = $nodeRequestData;
            }

            $response = $this->sendJSON($_request);

            // time out or no Mundipagg API response
            if ($response === false) {
                $helperLog->info('With MundiPagg Timeout');

                return false;
            }

            $errorReport = $helper->issetOr($response['ErrorReport'], false);

            // Error
            if ($errorReport) {
                $_errorItemCollection = $errorReport['ErrorItemCollection'];
                $errorCode = null;
                $errorDescription = null;

                foreach ($_errorItemCollection as $errorItem) {
                    $errorCode = $errorItem['ErrorCode'];
                    $errorDescription = $errorItem['Description'];
                }

                $helperLog->info('With Error description: ' . $errorDescription);

                return [
                    'error'            => 1,
                    'ErrorCode'        => $errorCode,
                    'ErrorDescription' => Mage::helper('mundipagg')->__($errorDescription),
                    'result'           => $response
                ];
            }

            $success = $helper->issetOr($response['Success']);

            // False
            if ($success === false) {
                $helperLog->info('With Success false');

                return [
                    'error'            => 1,
                    'ErrorCode'        => 'WithError',
                    'ErrorDescription' => 'WithError',
                    'result'           => $response
                ];
            } else {
                $helperLog->info('With Success true');

                $orderKey = $helper->issetOr($response['OrderResult']['OrderKey']);
                $orderReference = $helper->issetOr($response['OrderResult']['OrderReference']);
                $createDate = $helper->issetOr($response['OrderResult']['CreateDate']);

                // Success
                $result = [
                    'success'        => true,
                    'message'        => 0,
                    'OrderKey'       => $orderKey,
                    'OrderReference' => $orderReference,
                    'result'         => $response
                ];

                if (is_null($createDate) === false) {
                    $result['CreateDate'] = $createDate;
                }

                // remove boleto promo discount from session
                $this->removeBoletoPromoDiscount();

                return $result;
            }
        } catch (Exception $e) {
            //Redirect to Cancel page
            Mage::getSingleton('checkout/session')->setApprovalRequestSuccess('cancel');

            //Log error
            $helperLog->error($e, true);

            //Mail error
            $this->mailError(print_r($e->getMessage(), 1));

            // Return error
            $approvalRequest['error'] = 'Error WS';
            $approvalRequest['ErrorCode'] = 'ErrorCode WS';
            $approvalRequest['ErrorDescription'] = 'ErrorDescription WS';
            $approvalRequest['OrderKey'] = '';
            $approvalRequest['OrderReference'] = '';

            return $approvalRequest;
        }
    }

    /**
     * Convert BoletoTransaction Collection From Request
     */
    public function ConvertBoletoTransactionCollectionFromRequest($boletoTransactionCollectionRequest)
    {
        $newBoletoTransCollection = [];
        $counter = 0;

        foreach ($boletoTransactionCollectionRequest as $boletoTransItem) {
            $boletoTrans = [];

            $boletoTrans["AmountInCents"] = $boletoTransItem->AmountInCents - $this->getBoletoPromoDiscount();
            $boletoTrans["BankNumber"] = isset($boletoTransItem->BankNumber) ? $boletoTransItem->BankNumber : '';
            $boletoTrans["Instructions"] = $boletoTransItem->Instructions;
            $boletoTrans["DocumentNumber"] = $boletoTransItem->DocumentNumber;
            $boletoTrans["Options"]["CurrencyIso"] = $boletoTransItem->Options->CurrencyIso;
            $boletoTrans["Options"]["DaysToAddInBoletoExpirationDate"] = $boletoTransItem->Options->DaysToAddInBoletoExpirationDate;
            $boletoTrans['BillingAddress'] = $boletoTransItem->BillingAddress;
            $newBoletoTransCollection[$counter] = $boletoTrans;
            $counter += 1;
        }
        return $newBoletoTransCollection;
    }

    /**
     * Get boleto promo discount
     */
    private function getBoletoPromoDiscount()
    {
        $session = Mage::getSingleton('checkout/session');
        $boletoDiscount = $session->getData('boleto_promo_discount');
        return $boletoDiscount ?  $boletoDiscount * 100 : 0;
    }

    /**
     * Invalidate boleto promo discount
     */
    private function removeBoletoPromoDiscount()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setData('boleto_promo_discount', 0);
    }

    /**
     * Debit transaction
     **/
    public function debitTransaction($order, $data, Uecommerce_Mundipagg_Model_Standard $standard)
    {
        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

        try {
            // Get Webservice URL
            if ($standard->getEnvironment() == 'production') {
                $url = Mage::getStoreConfig('payment/mundipagg_debit/apiDebitUrl');
            } else {
                $url = Mage::getStoreConfig('payment/mundipagg_debit/apiDebitStagingUrl');
            }

            $requestData = $this->prepareDebitRequestData($order, $data, $standard);

            $jsonRequest = json_encode($requestData, JSON_PRETTY_PRINT);

            $response = $this->sendDataToApi($url, $jsonRequest, $standard->getMerchantKey(), $helperLog);
            $response = $this->responseFormatter($response);

            $helperLog->debug(
                "Order #" . $order->getIncrementId() .
                " | Response \n" .
                json_encode($response, JSON_PRETTY_PRINT)
            );

            return $this->debitResponseProcess($response);
        } catch (Exception $e) {
            //Redirect to Cancel page
            Mage::getSingleton('checkout/session')->setApprovalRequestSuccess('cancel');

            //Log error
            $helperLog->error($e, true);

            //Mail error
            $this->mailError(print_r($e->getMessage(), 1));

            // Return error
            $approvalRequest['error'] = 'Error WS';
            $approvalRequest['ErrorCode'] = 'ErrorCode WS';
            $approvalRequest['ErrorDescription'] = 'ErrorDescription WS';
            $approvalRequest['OrderKey'] = '';
            $approvalRequest['OrderReference'] = '';

            return $approvalRequest;
        }
    }

    /**
     * Set buyer data
     */
    public function buyerBillingData($order, $data, $_request, $standard)
    {
        if ($order->getData()) {
            $gender = null;

            if ($order->getCustomerGender()) {
                $gender = $order->getCustomerGender();
            }

            if ($order->getCustomerIsGuest() == 0) {
                $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
                $gender = $customer->getGender();
                $createdAt = explode(' ', $customer->getCreatedAt());
                $updatedAt = explode(' ', $customer->getUpdatedAt());
                $currentDateTime = Mage::getModel('core/date')->date('Y-m-d H:i:s');

                if (!array_key_exists(1, $createdAt)) {
                    $createdAt = explode(' ', $currentDateTime);
                }

                if (!array_key_exists(1, $updatedAt)) {
                    $updatedAt = explode(' ', $currentDateTime);
                }

                $createDateInMerchant = substr($createdAt[0] . 'T' . $createdAt[1], 0, 19);
                $lastBuyerUpdateInMerchant = substr($updatedAt[0] . 'T' . $updatedAt[1], 0, 19);
            } else {
                $createDateInMerchant = $lastBuyerUpdateInMerchant = date('Y-m-d') . 'T' . date('H:i:s');
            }

            switch ($gender) {
                case '1':
                    $gender = 'M';
                    break;
                case '2':
                    $gender = 'F';
                    break;
            }
        }

        $billingAddress = $order->getBillingAddress();
        $street = $billingAddress->getStreet();
        $regionCode = $billingAddress->getRegionCode();

        if ($billingAddress->getRegionCode() == '') {
            $regionCode = 'RJ';
        }

        $telephone = Mage::helper('mundipagg')->applyTelephoneMask($billingAddress->getTelephone());

        if ($billingAddress->getTelephone() == '') {
            $telephone = '55(21)88888888';
        }

        $data['DocumentNumber'] = $this->getCustomerDocumentNumber($data, $order);

        $invalid = 0;

        if (Mage::helper('mundipagg')->validateCPF($data['DocumentNumber'])) {
            $data['PersonType'] = 'Person';
            $data['DocumentType'] = 'CPF';
            $data['DocumentNumber'] = $data['DocumentNumber'];
        } else {
            $invalid++;
        }

        // We verify if a CNPJ is informed
        if (Mage::helper('mundipagg')->validateCNPJ($data['DocumentNumber'])) {
            $data['PersonType'] = 'Company';
            $data['DocumentType'] = 'CNPJ';
            $data['DocumentNumber'] = $data['DocumentNumber'];
        } else {
            $invalid++;
        }

        if ($invalid == 2) {
            $data['DocumentNumber'] = '00000000000';
            $data['DocumentType'] = 'CPF';
            $data['PersonType'] = 'Person';
        }

        // Request
        if ($gender == 'M' || $gender == 'F') {
            $_request["Buyer"]["Gender"] = $gender;
        }

        $_request["Buyer"]["DocumentNumber"] = preg_replace('[\D]', '', $data['DocumentNumber']);
        $_request["Buyer"]["DocumentType"] = $data['DocumentType'];
        $_request["Buyer"]["Email"] = $order->getCustomerEmail();
        $_request["Buyer"]["EmailType"] = 'Personal';
        $_request["Buyer"]["Name"] = $order->getCustomerName();
        $_request["Buyer"]["PersonType"] = $data['PersonType'];
        $_request["Buyer"]["MobilePhone"] = $telephone;
        $_request["Buyer"]['BuyerCategory'] = 'Normal';
        $_request["Buyer"]['FacebookId'] = '';
        $_request["Buyer"]['TwitterId'] = '';
        $_request["Buyer"]['BuyerReference'] = '';
        $_request["Buyer"]['CreateDateInMerchant'] = $createDateInMerchant;
        $_request["Buyer"]['LastBuyerUpdateInMerchant'] = $lastBuyerUpdateInMerchant;

        // Address
        $address = [];
        $address['AddressType'] = 'Residential';
        $address['City'] = $billingAddress->getCity();
        $address['District'] = isset($street[3]) ? $street[3] : 'xxx';
        $address['Complement'] = isset($street[2]) ? $street[2] : '';
        $address['Number'] = isset($street[1]) ? $street[1] : '0';
        $address['State'] = $regionCode;
        $address['Street'] = isset($street[0]) ? $street[0] : 'xxx';
        $address['ZipCode'] = preg_replace('[\D]', '', $billingAddress->getPostcode());
        $address['Country'] = 'Brazil';

        $_request["Buyer"]["AddressCollection"] = [];
        $_request["Buyer"]["AddressCollection"] = [$address];

        return $_request["Buyer"];
    }

    /**
     * Set buyer data
     */
    public function buyerDebitBillingData($order, $data, $_request, $standard)
    {
        if ($order->getData()) {
            if ($order->getCustomerGender()) {
                $gender = $order->getCustomerGender();
            } else {
                $customerId = $order->getCustomerId();
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $gender = $customer->getGender();
            }

            switch ($gender) {
                case '1':
                    $gender = 'M';
                    break;
                case '2':
                    $gender = 'F';
                    break;
            }
        }

        $billingAddress = $order->getBillingAddress();
        $street = $billingAddress->getStreet();
        $zipCode = preg_replace('[\D]', '', $billingAddress->getPostcode());
        $regionCode = $billingAddress->getRegionCode();

        if ($billingAddress->getRegionCode() == '') {
            $regionCode = 'RJ';
        }

        if ($billingAddress->getCountry() == 'BR') {
            $regionCode = Mage::helper('mundipagg')->getUfByCep($zipCode);
        }

        $telephone = Mage::helper('mundipagg')->applyTelephoneMask($billingAddress->getTelephone());

        if ($billingAddress->getTelephone() == '') {
            $telephone = '55(21)88888888';
        }

        $testCpfCnpj = Mage::getStoreConfig('mundipagg_tests_cpf_cnpj');

        if ($testCpfCnpj != '') {
            $data['TaxDocumentNumber'] = $testCpfCnpj;
        }

        $data['DocumentNumber'] = $this->getCustomerDocumentNumber($data, $order);

        $invalid = 0;

        if (Mage::helper('mundipagg')->validateCPF($data['DocumentNumber'])) {
            $data['PersonType'] = 'Person';
            $data['DocumentType'] = 'CPF';
            $data['DocumentNumber'] = $data['DocumentNumber'];
        } else {
            $invalid++;
        }
        // We verify if a CNPJ is informed
        if (Mage::helper('mundipagg')->validateCNPJ($data['DocumentNumber'])) {
            $data['PersonType'] = 'Company';
            $data['DocumentType'] = 'CNPJ';
            $data['DocumentNumber'] = $data['DocumentNumber'];
        } else {
            $invalid++;
        }
        if ($invalid == 2) {
            $data['DocumentNumber'] = '00000000000';
            $data['DocumentType'] = 'CPF';
            $data['PersonType'] = 'Person';
        }

        // Request
        if ($gender == 'M' || $gender == 'F') {
            $_request["Buyer"]["Gender"] = $gender;
            $_request["Buyer"]["GenderEnum"] = $gender;
        }

        $_request["Buyer"]["TaxDocumentNumber"] = preg_replace('[\D]', '', $data['DocumentNumber']);
        $_request["Buyer"]["TaxDocumentTypeEnum"] = $data['DocumentType'];
        $_request["Buyer"]["Email"] = $order->getCustomerEmail();
        $_request["Buyer"]["EmailType"] = 'Personal';
        $_request["Buyer"]["Name"] = $order->getCustomerName();
        $_request["Buyer"]["PersonType"] = $data['PersonType'];
        $_request['Buyer']['PhoneRequestCollection'] = Mage::helper('mundipagg')->getPhoneRequestCollection($order);
        //$_request["Buyer"]["MobilePhone"] 			= $telephone;
        $_request["Buyer"]['BuyerCategory'] = 'Normal';
        $_request["Buyer"]['FacebookId'] = '';
        $_request["Buyer"]['TwitterId'] = '';
        $_request["Buyer"]['BuyerReference'] = '';

        // Address
        $address = [];
        $address['AddressTypeEnum'] = 'Residential';
        $address['City'] = $billingAddress->getCity();
        $address['District'] = isset($street[3]) ? $street[3] : 'xxx';
        $address['Complement'] = isset($street[2]) ? $street[2] : '';
        $address['Number'] = isset($street[1]) ? $street[1] : '0';
        $address['State'] = $regionCode;
        $address['Street'] = isset($street[0]) ? $street[0] : 'xxx';

        if (strlen($address['Number']) > 8) {
            $address['Street'] .= ", " . $address['Number'];
            $address['Number'] = '0';
        }

        $address['ZipCode'] = $zipCode;

        $_request["Buyer"]["BuyerAddressCollection"] = [];
        $_request["Buyer"]["BuyerAddressCollection"] = [$address];

        return $_request["Buyer"];
    }

    /**
     * Set cart data
     */
    public function cartData($order, $data, $_request, $standard)
    {
        $baseGrandTotal = round($order->getBaseGrandTotal(), 2);
        $baseDiscountAmount = round($order->getBaseDiscountAmount(), 2);

        $discount = 1;

        $shippingDiscountAmount = round($order->getShippingDiscountAmount(), 2);

        if (abs($shippingDiscountAmount) > 0) {
            $totalShippingWithoutDiscount = round($order->getBaseShippingInclTax(), 2);
            $totalShippingWithDiscount = $totalShippingWithoutDiscount - abs($shippingDiscountAmount);
            $shippingDiscount = round(($totalShippingWithDiscount / $totalShippingWithoutDiscount), 4);
        } else {
            $shippingDiscount = 1;
        }

        $items = [];

        foreach ($order->getItemsCollection() as $item) {
            if ($item->getParentItemId() == '') {
                $items[$item->getItemId()]['sku'] = $item->getProductId();
                $items[$item->getItemId()]['name'] = $item->getName();
                $items[$item->getItemId()]['description'] = Mage::getModel('catalog/product')->load($item->getProductId())->getShortDescription();
                $items[$item->getItemId()]['qty'] = round($item->getQtyOrdered(), 0);
                $items[$item->getItemId()]['price'] = $item->getBasePrice();
            }
        }

        $i = 0;

        $shipping = intval(strval(($order->getBaseShippingInclTax() * $shippingDiscount * 100)));
        $deadlineConfig = Mage::getStoreConfig('payment/mundipagg_standard/delivery_deadline');

        if ($deadlineConfig != '') {
            $date = new Zend_Date($order->getCreatedAtStoreDate()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT), Zend_Date::DATETIME);
            $date->addDay((int)$deadlineConfig);
            $deliveryDeadline = $date->toString('yyyy-MM-ddTHH:mm:ss');
            $_request["ShoppingCartCollection"]['DeliveryDeadline'] = $deliveryDeadline;
            $_request["ShoppingCartCollection"]['EstimatedDeliveryDate'] = $deliveryDeadline;
        }

        $_request["ShoppingCartCollection"]["FreightCostInCents"] = $shipping;
        $_request['ShoppingCartCollection']['ShippingCompany'] = Mage::getStoreConfig('payment/mundipagg_standard/shipping_company');

        foreach ($items as $itemId) {
            $unitCostInCents = intval(strval(($itemId['price'] * $discount * 100)));
            $_request["ShoppingCartCollection"]["ShoppingCartItemCollection"][$i]["Description"] = empty($itemId['description']) || ($itemId['description'] == '') ? $itemId['name'] : $itemId['description'];
            $_request["ShoppingCartCollection"]["ShoppingCartItemCollection"][$i]["ItemReference"] = $itemId['sku'];
            $_request["ShoppingCartCollection"]["ShoppingCartItemCollection"][$i]["Name"] = $itemId['name'];
            $_request["ShoppingCartCollection"]["ShoppingCartItemCollection"][$i]["Quantity"] = $itemId['qty'];
            $_request["ShoppingCartCollection"]["ShoppingCartItemCollection"][$i]["UnitCostInCents"] = $unitCostInCents;
            //}
            $totalInCents = intval(strval(($itemId['qty'] * $itemId['price'] * $discount * 100)));

            $_request["ShoppingCartCollection"]["ShoppingCartItemCollection"][$i]["TotalCostInCents"] = $totalInCents;

            $i++;
        }

        // Delivery address
        if ($order->getIsVirtual()) {
            $addy = $order->getBillingAddress();
        } else {
            $addy = $order->getShippingAddress();
        }

        $street = $addy->getStreet();
        $regionCode = $addy->getRegionCode();

        if ($addy->getRegionCode() == '') {
            $regionCode = 'RJ';
        }

        $address = [];
        $address['City'] = $addy->getCity();
        $address['District'] = isset($street[3]) ? $street[3] : 'xxx';
        $address['Complement'] = isset($street[2]) ? $street[2] : '';
        $address['Number'] = isset($street[1]) ? $street[1] : '0';
        $address['State'] = $regionCode;
        $address['Street'] = isset($street[0]) ? $street[0] : 'xxx';
        $address['ZipCode'] = preg_replace('[\D]', '', $addy->getPostcode());
        $address['Country'] = 'Brazil';
        $address['AddressType'] = "Shipping";

        $_request["ShoppingCartCollection"]["DeliveryAddress"] = [];
        $_request["ShoppingCartCollection"]["DeliveryAddress"] = $address;

        return [$_request["ShoppingCartCollection"]];
    }

    /**
     * Manage Order Request: capture / void / refund
     **/
    public function manageOrderRequest($data, Uecommerce_Mundipagg_Model_Standard $standard)
    {
        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

        try {
            // Get Webservice URL
            $url = "{$standard->getURL()}{$data['ManageOrderOperationEnum']}";

            unset($data['ManageOrderOperationEnum']);

            // Get store key
            $key = $standard->getMerchantKey();
            $dataToPost = json_encode($data);

            $helperUtil = new Uecommerce_Mundipagg_Helper_Util();
            $helperLog->debug("Url: {$url}");
            $helperLog->info("Request:\n{$helperUtil->jsonEncodePretty($data)}\n");

            // Send payment data to MundiPagg
            $ch = curl_init();

            // Header
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'MerchantKey: ' . $key . ''));

            // Set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataToPost);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Execute post
            $_response = curl_exec($ch);

            //check for curl errors
            $curlErrorNumber = curl_errno($ch);

            if ($curlErrorNumber !== 0) {
                $helperLog->error("CURL ERROR ". $curlErrorNumber . "!" . curl_error($ch));
            }

            $xml = simplexml_load_string($_response);

            // Check for invalid xml response
            if ($xml === false) {
                $helperLog->error("INVALID XML RESPONSE!\n" . $_response . "\n\n");
            }

            $json = $helperUtil->jsonEncodePretty($xml);

            // Close connection
            curl_close($ch);

            $helperLog->info("Response:\n{$json}\n");

            return ['result' => simplexml_load_string($_response)];
        } catch (Exception $e) {
            //Redirect to Cancel page
            Mage::getSingleton('checkout/session')->setApprovalRequestSuccess(false);

            //Log error
            $helperLog->error($e, true);

            //Mail error
            $this->mailError(print_r($e->getMessage(), true));

            // Throw Exception
            Mage::throwException(Mage::helper('mundipagg')->__('Payment Error'));
        }
    }

    /**
     * call MundiPagg endpoint '/Sale/Capture'
     *
     * @param array  $data
     * @param string $orderReference
     * @return array
     */
    public function saleCapture($data, $orderReference)
    {
        $log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $log->setLogLabel("#{$orderReference}");

        // Get Webservice URL
        $url = "{$this->modelStandard->getURL()}Capture";

        // Get store key
        $key = $this->modelStandard->getmerchantKey();
        $dataToPost = json_encode($data);

        /* @var Uecommerce_Mundipagg_Helper_Data $helper */
        $helper = Mage::helper('mundipagg');
        $log->debug("Url: {$url}");
        $log->info("Request:\n{$helper->jsonEncodePretty($data)}\n");

        // Send payment data to MundiPagg
        $ch = curl_init();

        // Header
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "MerchantKey: {$key}",
            'Accept: application/json'
        ]);

        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataToPost);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute post
        $rawResponse = curl_exec($ch);

        //check for curl errors
        $curlErrorNumber = curl_errno($ch);
        if ($curlErrorNumber !== 0) {
            $log->error("CURL ERROR ". $curlErrorNumber . "!" . curl_error($ch));
        }

        // Close connection
        curl_close($ch);

        $response = json_decode($rawResponse, true);

        // Check for invalid json response
        if ($response === null) {
            $log->error("INVALID JSON RESPONSE!\n" . $rawResponse . "\n\n");
        }

        $jsonPretty = $helper->jsonEncodePretty($response);

        $log->info("Response:\n{$jsonPretty}\n");
        // Return
        return $response;
    }

    /**
     * Process order
     * @param $order
     * @param $data
     */
    public function processOrder($postData)
    {
        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $returnMessage = '';

        try {
            if (!isset($postData['xmlStatusNotification'])) {
                $helperLog->info("Index xmlStatusNotification not found");
                return 'KO | Internal error.';
            }

            $xmlStatusNotificationString = htmlspecialchars_decode($postData['xmlStatusNotification']);

            $xml = simplexml_load_string($xmlStatusNotificationString);
            $json = json_encode($xml);
            $data = json_decode($json, true);

            $orderReference = isset($xml->OrderReference) ? $xml->OrderReference : null;

            if (is_null($orderReference)) {
                $logMessage = "Notification post:\n{$xmlStatusNotificationString}\n";
            } else {
                $logMessage = "Notification post for order #{$orderReference}:\n{$xmlStatusNotificationString}";
            }

            $helperLog->info($logMessage);
            $orderReference = $data['OrderReference'];

            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($orderReference);

            if (!$order->getId()) {
                $returnMessage = "OrderReference don't correspond to a store order.";
                $helperLog->info("OrderReference: {$orderReference} | {$returnMessage}");
                return "OK | {$returnMessage}";
            }

            $transactionData = null;

            if (!empty($data['BoletoTransaction'])) {
                $status = $data['BoletoTransaction']['BoletoTransactionStatus'];
                $transactionKey = $data['BoletoTransaction']['TransactionKey'];
                $capturedAmountInCents = $data['BoletoTransaction']['AmountPaidInCents'];
                $transactionData = $data['BoletoTransaction'];
            }

            if (!empty($data['CreditCardTransaction'])) {
                $status = $data['CreditCardTransaction']['CreditCardTransactionStatus'];
                $transactionKey = $data['CreditCardTransaction']['TransactionKey'];
                $capturedAmountInCents = $data['CreditCardTransaction']['CapturedAmountInCents'];
                $transactionData = $data['CreditCardTransaction'];
            }

            if (!empty($data['OnlineDebitTransaction'])) {
                $status = $data['OnlineDebitTransaction']['OnlineDebitTransactionStatus'];
                $transactionKey = $data['OnlineDebitTransaction']['TransactionKey'];
                $capturedAmountInCents = $data['OnlineDebitTransaction']['AmountPaidInCents'];
                $transactionData = $data['OnlineDebitTransaction'];
            }

            $returnMessageLabel = "Order #{$order->getIncrementId()}";

            if (!isset($transactionData['IsRecurrency'])) {
                $transactionData['IsRecurrency'] = 'false';
            }

            //If is recurrency order and it status is processing or canceled stop the execution
            if(($order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING ||
                $order->getState() == Mage_Sales_Model_Order::STATE_CANCELED) &&
                $transactionData['IsRecurrency'] === 'true'
            ) {
                $message = 'This is a recurrency order and its status is already';
                $returnMessage = "OK | Order #{$orderReference} | {$message} " . $order->getState();

                $helperLog->info($returnMessage);

                return $returnMessage;
            }

            if (isset($data['OrderStatus'])) {
                $orderStatus = $data['OrderStatus'];

                //if Magento order is not processing and MundiPagg order status is canceled, cancel the order on Magento
                if ($order->getState() != Mage_Sales_Model_Order::STATE_PROCESSING && $orderStatus == Uecommerce_Mundipagg_Model_Enum_OrderStatusEnum::CANCELED) {
                    switch ($order->getState()) {
                        case Mage_Sales_Model_Order::STATE_CANCELED:
                            $returnMessage = 'OK | ' . $returnMessageLabel . ' | Order already canceled.';
                            $helperLog->info($returnMessage);

                            return $returnMessage;
                        case Mage_Sales_Model_Order::STATE_COMPLETE:
                            foreach ($order->getInvoiceCollection() as $invoice) {
                                if ($invoice->canRefund()) {
                                    $invoices[] = $invoice;
                                }
                            }

                            if (!empty($invoices)) {
                                $service = Mage::getModel('sales/service_order', $order);
                                foreach ($invoices as $invoice) {
                                    $this->closeInvoice($invoice);
                                    $this->createCreditMemo($invoice, $service);
                                }
                            }

                            $this->closeOrder($order);
                            $returnMessage = 'OK | ' . $returnMessageLabel . ' | Order closed.';
                            $helperLog->info($returnMessage);

                            return $returnMessage;
                    }

                    try {
                        // set flag to prevent send back a cancelation to Mundi via API
                        $this->setCanceledByNotificationFlag($order, true);
                        $this->tryCancelOrder($order, "Transaction update received: {$status}");
                        $returnMessage = "OK | {$returnMessageLabel} | Canceled successfully";

                        $helperLog->info($returnMessage);
                    } catch (Exception $e) {
                        $returnMessage = "KO | {$returnMessageLabel} | {$e->getMessage()}";

                        $helperLog->error($returnMessage);
                    }

                    return $returnMessage;
                }
            }

            $payment = $order->getPayment();

            // We check if transactionKey exists in database
            $t = $this->getLocalTransactionsQty($order->getId(), $transactionKey);

            if ($t <= 0) {
                $helperLog->setLogLabel("Order #{$orderReference}");
                $helperLog->info("TransactionKey {$transactionKey} not found on database for this order.");
                $helperLog->info("Searching order history...");
                $helperLog->setLogLabel("");

                $mundiQueryResult = $this->getOrderTransactions($orderReference);
                $processQueryResult = $this->processQueryResults($mundiQueryResult, $payment);

                if ($processQueryResult) {
                    $this->removeIntegrationErrorInfo($order);
                }
            }

            // We check if transactionKey exists in database again, after query MundiPagg transactions
            $t = $this->getLocalTransactionsQty($order->getId(), $transactionKey);

            if ($t <= 0) {
                $errMsg = "OK | Order #{$orderReference} | TransactionKey {$transactionKey} not found for this order";
                $helperLog->info($errMsg);

                return $errMsg;
            }

            $order->addStatusHistoryComment("MP - Transaction update received: {$status}", false);
            $order->save();
            // transactionKey has been found so we can proceed
            
            $statusWithError = Uecommerce_Mundipagg_Model_Enum_CreditCardTransactionStatusEnum::WITH_ERROR;
            $statusWithError = strtolower($statusWithError);

            if (empty($capturedAmountInCents) === false) {
                $amountToCapture = $capturedAmountInCents * 0.01;
            }

            $amountInCents = intval($data['AmountInCents']);

            return $this->processOrderStatus(
                $status,
                $order,
                $amountToCapture,
                $transactionKey,
                $orderReference,
                $returnMessageLabel,
                $capturedAmountInCents,
                $data,
                $transactionData,
                $statusWithError,
                $amountInCents);
        } catch (Exception $e) {
            $returnMessage = "Internal server error | {$e->getCode()} - ErrMsg: {$e->getMessage()}";

            //Log error
            $helperLog->error($e, true);

            //Mail error
            $this->mailError(print_r($e->getMessage(), 1));

            return "KO | {$returnMessage}";
        }
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param string                 $comment
     * @return bool
     * @throws RuntimeException
     */
    public function tryCancelOrder(Mage_Sales_Model_Order $order, $comment = null)
    {
        if ($order->canCancel()) {
            try {
                $order->cancel();

                if (!is_null($comment) && is_string($comment)) {
                    $order->addStatusHistoryComment("MP - " . $comment);
                }

                $order->save();

                return true;
            } catch (Exception $e) {
                throw new RuntimeException("Order cannot be canceled. Error reason: {$e->getMessage()}");
            }
        } else {
            throw new RuntimeException("Order cannot be canceled.");
        }
    }

    /**
     * Create invoice
     * @todo must be deprecated use Uecommerce_Mundipagg_Model_Order_Payment createInvoice
     * @param Mage_Sales_Model_Order $order
     * @param array $data
     * @param float $totalPaid
     * @param string $status
     * @return string OK|KO
     */
    private function createInvoice($order, $data, $totalPaid, $status)
    {
        $helperOrderStatus = Mage::helper('mundipagg/processOrderStatus');

        return $helperOrderStatus->createInvoice($order, $data, $totalPaid, $status);
    }

    /**
     * Search by orderkey
     * @param string $orderKey
     * @return array
     */
    public function getTransactionHistory($orderKey)
    {
        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

        // @var $standard Uecommerce_Mundipagg_Model_Standard
        $standard = Mage::getModel('mundipagg/standard');

        // Get store key
        $key = $standard->getMerchantKey();

        // Get Webservice URL
        $url = $standard->getURL() . '/Query/' . http_build_query(array('OrderKey' => $orderKey));

        // get transactions from MundiPagg
        $ch = curl_init();

        // Header
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'MerchantKey: ' . $key . ''));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute get
        $_response = curl_exec($ch);

        //check for curl errors
        $curlErrorNumber = curl_errno($ch);
        if ($curlErrorNumber !== 0) {
            $helperLog->error("CURL ERROR ". $curlErrorNumber . "!" . curl_error($ch));
        }

        // Close connection
        curl_close($ch);

        $util = new Uecommerce_Mundipagg_Helper_Util();

        $helperLog->info($util->xmlToJson($_response));

        return ['result' => simplexml_load_string($_response)];
    }

    /**
     * Mail error to Mage::getStoreConfig('trans_email/ident_custom1/email')
     *
     * @since 31-05-2016
     * @param string $message
     */
    public function mailError($message = '')
    {
        $mail = new Zend_Mail();

        $fromName = Mage::getStoreConfig('trans_email/ident_sales/name');
        $fromEmail = Mage::getStoreConfig('trans_email/ident_sales/email');
        $toEmail = Mage::getStoreConfig('trans_email/ident_custom1/email');
        $toName = Mage::getStoreConfig('trans_email/ident_custom1/name');
        $bcc = [];
        $subject = 'Error Report - MundiPagg Magento Integration';
        $body = "Error Report from: {$_SERVER['HTTP_HOST']}<br><br>{$message}";

        $mail->setFrom($fromEmail, $fromName)
            ->addTo($toEmail, $toName)
            ->addBcc($bcc)
            ->setSubject($subject)
            ->setBodyHtml($body);

        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

        try {
            $mail->send();
            $helperLog->info("Error Report Sent: {$message}");
        } catch (Exception $e) {
            $helperLog->error($e);
        }
    }

    /**
     * Get 'RequestData' node for the One v2 request if antifraud is enabled
     *
     * @author Ruan Azevedo <razvedo@mundipagg.com>
     * @since 06-01-2016
     * @throws Mage_Core_Exception
     * @return array $requestData
     */
    private function getRequestDataNode()
    {
        $antifraud = Mage::getStoreConfig('payment/mundipagg_standard/antifraud');
        $antifraudProvider = Mage::getStoreConfig('payment/mundipagg_standard/antifraud_provider');

        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $helperHttpCore = new Mage_Core_Helper_Http();

        $customerIp = $helperHttpCore->getRemoteAddr();

        $outputMsg = '';
        $sessionId = '';

        $error = false;

        $requestData = [
            'IpAddress' => $customerIp,
            'SessionId' => ''
        ];

        if ($antifraud == false) {
            return false;
        }

        if ($this->debugEnabled) {
            $helperLog->debug("Antifraud enabled...");
        }

        switch ($antifraudProvider) {
            case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_NONE:
                $outputMsg = "Antifraud enabled and none antifraud provider selected at module configuration.";
                $error = true;
                break;
            case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_CLEARSALE:
                $outputMsg = "Antifraud provider: Clearsale";
                $sessionId = Uecommerce_Mundipagg_Model_Customer_Session::getSessionId();
                break;
            case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_FCONTROL:
                $outputMsg = "Antifraud provider: FControl";
                $sessionId = Uecommerce_Mundipagg_Model_Customer_Session::getSessionId();
                break;
        }

        if ($error) {
            $helperLog->error($outputMsg, true);
        }

        if (is_null($sessionId)) {
            $sessionId = '';
        }

        $requestData['SessionId'] = $sessionId;
        $helperLog->info($outputMsg);

        return $requestData;
    }

    private function clearAntifraudDataFromSession()
    {
        $customerSession = Mage::getSingleton('customer/session');
        $customerSession->unsetData(Uecommerce_Mundipagg_Model_Customer_Session::SESSION_ID);
    }

    /**
     * Method to unify the transactions requests and his logs
     * @todo must be deprecated, use Uecommerce_Mundipagg_Model_Api::sendJSON method
     *
     * @since 05-24-2016
     * @param array  $dataToPost
     * @param string $url
     * @param array  $_logRequest
     * @return array $_response
     */
    public function sendRequest($dataToPost, $url, $_logRequest = [])
    {
        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

        if (empty($dataToPost) || empty($url)) {
            $errMsg = __METHOD__ . "Exception: one or more arguments not informed to request";
            $helperLog->error($errMsg);

            throw new InvalidArgumentException($errMsg);
        }

        if (empty($_logRequest)) {
            $_logRequest = $dataToPost;
        }

        $requestRawJson = json_encode($dataToPost);
        $requestJSON = $this->helperUtil->jsonEncodePretty($_logRequest);
        $helperLog->info("Request:\n{$requestJSON}\n");

        $ch = curl_init();

        // Header
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'MerchantKey: ' . $this->modelStandard->getMerchantKey() . ''));

        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestRawJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $timeoutLimit = Mage::getStoreConfig('payment/mundipagg_standard/integration_timeout_limit');

        if (is_null($timeoutLimit) === false) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutLimit);
        }

        // Execute post
        $_response = curl_exec($ch);

        //check for curl errors
        $curlErrorNumber = curl_errno($ch);
        if ($curlErrorNumber !== 0) {
            $helperLog->error("CURL ERROR ". $curlErrorNumber . "!" . curl_error($ch));
        }

        // Close connection
        curl_close($ch);

        // Is there an error?
        $xml = simplexml_load_string($_response);

        // Check for invalid xml response
        if ($xml === false) {
            $helperLog->error("INVALID XML RESPONSE!\n" . $_response . "\n\n");
        }

        $responseJSON = $this->helperUtil->jsonEncodePretty($xml);
        $responseArray = json_decode($responseJSON, true);

        if ($_response != 'false') {
            $helperLog->info("Response:\n{$responseJSON} \n");
        } else {
            $helperLog->warning("Response: Integration timeout!");
        }

        $responseData = [
            'xmlData'   => $xml,
            'arrayData' => $responseArray
        ];

        $this->clearAntifraudDataFromSession();

        return $responseData;
    }

    /**
     * @param array $data
     * @return array
     */
    public function sendJSON($data)
    {
        $log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

        $merchantKey = $this->modelStandard->getMerchantKey();
        $url = $this->modelStandard->getUrl();
        $environment = $this->modelStandard->getEnvironment();

        if (empty($data)) {
            $errMsg = __METHOD__ . "Exception: one or more arguments not informed to request";
            $log->error($errMsg, true);

            throw new InvalidArgumentException($errMsg);
        }

        $helper = Mage::helper('mundipagg');
        $orderReference = $helper->issetOr($data['Order']['OrderReference']);

        if (is_null($orderReference) === false) {
            $log->setLogLabel("Order #{$orderReference}");
        }

        $requestRaw = json_encode($data);

        $moduleVersion = $version = Mage::helper('mundipagg')
            ->getExtensionVersion();

        $headers = [
            'Content-Type: application/json',
            "MerchantKey: {$merchantKey}",
            'Accept: JSON',
            'MagentoOne: ' . $moduleVersion
        ];

        $log->info(
            "\n Request url: " . $url .
            "\n Environment: ". $environment .
            "\n Headers: \n" .
            json_encode($headers, JSON_PRETTY_PRINT)
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestRaw);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $timeoutLimit = Mage::getStoreConfig('payment/mundipagg_standard/integration_timeout_limit');

        if (is_null($timeoutLimit) === false) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutLimit);
        }

        // Execute post
        $response = curl_exec($ch);

        //check for curl errors
        $curlErrorNumber = curl_errno($ch);

        if ($curlErrorNumber !== 0) {
            $log->error("CURL ERROR ". $curlErrorNumber . "!" . curl_error($ch));
        }

        // Close connection
        curl_close($ch);

        $responseData = json_decode($response, true);

        // Check for invalid json response
        if ($responseData === null) {
            $log->error("INVALID JSON RESPONSE!\n" . $response . "\n\n");
        }

        $requestObfuscated = $this->hideCustomerData($data);

        $requestPretty = $this->helperUtil->jsonEncodePretty($requestObfuscated);
        $responsePretty = $this->helperUtil->jsonEncodePretty($responseData);

        $this->clearAntifraudDataFromSession();

        // log Request JSON
        $log->info("Request:\n{$requestPretty}\n");

        if ($response == 'false') {
            $log->warning("Response: Integration timeout!");

            return false;
        }

        if (!$responsePretty) {
            $responsePretty = $response;
        }

        $log->info("Response:\n{$responsePretty}\n");

        return $responseData;
    }

    /**
     * Check if order is in offline retry time
     * @deprecated since version 2.9.20
     * @author Ruan Azevedo <razevedo@mundipagg.com>
     * @since 2016-06-20
     * @param string $orderIncrementId
     * @return boolean
     */
    public function orderIsInOfflineRetry($orderIncrementId)
    {
        $model = Mage::getModel('mundipagg/offlineretry');
        $offlineRetry = $model->loadByIncrementId($orderIncrementId);
        $deadline = $offlineRetry->getDeadline();

        $now = new DateTime();
        $deadline = new DateTime($deadline);

        if ($now < $deadline) {
            // in offline retry yet
            return true;
        } else {
            // offline retry time is over
            return false;
        }
    }

    /**
     * If the Offline Retry feature is enabled, save order offline retry statements
     *
     * @author Ruan Azevedo <razevedo@mundipagg.com>
     * @deprecated since version 2.9.20
     * @since 2016-06-23
     * @param string   $orderIncrementId
     * @param DateTime $createDate
     */
    private function saveOfflineRetryStatements($orderIncrementId, DateTime $createDate)
    {
        // is offline retry is enabled, save statements
        if (Uecommerce_Mundipagg_Model_Offlineretry::offlineRetryIsEnabled()) {
            $offlineRetryTime = Mage::getStoreConfig('payment/mundipagg_standard/delayed_retry_max_time');
            $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
            $offlineRetryLogLabel = "Order #{$orderIncrementId} | offline retry statements";
            $model = new Uecommerce_Mundipagg_Model_Offlineretry();
            $offlineRetry = $model->loadByIncrementId($orderIncrementId);

            try {
                $offlineRetry->setOrderIncrementId($orderIncrementId);
                $offlineRetry->setCreateDate($createDate->getTimestamp());

                $deadline = new DateTime();
                $interval = new DateInterval('PT' . $offlineRetryTime . 'M');
                $deadline->setTimestamp($createDate->getTimestamp());
                $deadline->add($interval);

                $offlineRetry->setDeadline($deadline->getTimestamp());
                $offlineRetry->save();
                $helperLog->info("{$offlineRetryLogLabel} saved successfully.");
            } catch (Exception $e) {
                $helperLog->error("{$offlineRetryLogLabel} cannot be saved: {$e}");
            }
        }
    }

    public function getLocalTransactionsQty($orderId, $transactionKey)
    {
        $qty = 0;
        $transactions = Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addAttributeToFilter('order_id', array('eq' => $orderId));

        foreach ($transactions as $key => $transaction) {
            $orderTransactionKey = $transaction->getAdditionalInformation('TransactionKey');

            // transactionKey found
            if ($orderTransactionKey == $transactionKey) {
                $qty++;

                continue;
            }
        }

        return $qty;
    }

    /**
     * @param string|int $orderReference
     * @return array
     */
    public function getOrderTransactions($orderReference)
    {
        $log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $log->setLogLabel("Order {$orderReference}");

        $headers = [
            'Content-Type: application/json',
            "MerchantKey: {$this->modelStandard->getMerchantKey()}",
            'Accept: JSON'
        ];

        $url = $this->modelStandard->getUrl() . "Query/OrderReference={$orderReference}";

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $responseRaw = curl_exec($ch);

        //check for curl errors
        $curlErrorNumber = curl_errno($ch);
        if ($curlErrorNumber !== 0) {
            $log->error("CURL ERROR ". $curlErrorNumber . "!" . curl_error($ch));
        }

        curl_close($ch);

        $util = new Uecommerce_Mundipagg_Helper_Util();

        $responseData = json_decode($responseRaw, true);

        // Check for invalid json response
        if ($responseData === null) {
            $log->error("INVALID JSON RESPONSE!\n" . $responseRaw . "\n\n");
        }

        $responseJSON = $util->jsonEncodePretty($responseData);

        $log->info("Request: {$url}");
        $log->info("Response:\n{$responseJSON}");

        return $responseData;
    }

    public function getOrderTxnByTransactionKey($orderId, $transactionKey)
    {
        $transactionFound = null;
        $transactions = Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addAttributeToFilter('order_id', array('eq' => $orderId));

        foreach ($transactions as $key => $transaction) {
            $orderTransactionKey = $transaction->getAdditionalInformation('TransactionKey');

            // transactionKey found
            if ($orderTransactionKey == $transactionKey) {
                $transactionFound = $transaction;

                continue;
            }
        }

        return $transactionFound;
    }

    public function hideCustomerData($data)
    {
        $helper = Mage::helper('mundipagg');

        $ccTxnsCollection = null;
        $transactions = [];

        // request json
        $ccTxnsCollection = $helper->issetOr($data['CreditCardTransactionCollection']);

        // if not a request json, check for an response json
        if (is_null($ccTxnsCollection)) {
            $ccTxnsCollection = $helper->issetOr($data['CreditCardTransactionResultCollection']);
        }

        // if none transaction collection found, nothing to do here
        if (is_null($ccTxnsCollection)) {
            return $data;
        }

        // for each transaction, check sensible fields and obfuscate them
        foreach ($ccTxnsCollection as $transaction) {
            $creditCard = $helper->issetOr($transaction['CreditCard']);
            $ccSensibleFields = ['CreditCardNumber', 'SecurityCode', 'ExpMonth', 'ExpYear', 'InstantBuyKey'];

            foreach ($ccSensibleFields as $idx) {
                $fieldValue = $helper->issetOr($creditCard[$idx]);

                if (is_null($fieldValue) === false) {
                    $transaction['CreditCard'][$idx] = $helper->obfuscate($fieldValue);
                }
            }

            $transactions[] = $transaction;
        }

        $ccTransactionCollectionObfuscated = $transactions;

        $data['CreditCardTransactionCollection'] = $ccTransactionCollectionObfuscated;

        // Buyer node
        $buyer = $helper->issetOr($data['Buyer']);
        if (is_null($buyer)) {
            return $data;
        }

        // check Buyer sensible fields and obfuscate them
        $buyerSensibleFields = ['DocumentNumber', 'Email', 'HomePhone', 'MobilePhone'];

        foreach ($buyerSensibleFields as $idx) {
            $fieldValue = $helper->issetOr($buyer[$idx]);

            if (is_null($fieldValue) === false) {
                $data['Buyer'][$idx] = $helper->obfuscate($fieldValue);
            }
        }

        return $data;
    }

    /**
     * @param object $order
     * @return boolean
     */
    private function closeOrder($order)
    {
        $order->setData('state', Mage_Sales_Model_Order::STATE_CLOSED);
        $order->setStatus(Mage_Sales_Model_Order::STATE_CLOSED);
        $order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CLOSED, "Transaction update received: " . Mage_Sales_Model_Order::STATE_CLOSED, true);
        $order->sendOrderUpdateEmail();

        if ($order->save()){
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param object $invoice
     * @return boolean
     */
    private function closeInvoice($invoice)
    {
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_CANCELED);

        if ($invoice->save()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param object $invoice
     * @return boolean
     */
    private function createCreditMemo($invoice, $service)
    {
        $creditmemo = $service->prepareInvoiceCreditmemo($invoice);
        $creditmemo->setOfflineRequested(true);

        if ($creditmemo->register()->save()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * @param Array $transactionCollectionArray
     * @return Array Not authorized transaction
     */
    private function ifOneOrMoreTransactionFailed($transactionCollectionArray)
    {
        foreach ($transactionCollectionArray as $transaction) {
            if(!isset($transaction['Success']) || $transaction['Success'] == 0){
                $notAuthorizedTransaction = [
                    'error'            => 1,
                    'ErrorCode'        => $transaction['AcquirerReturnCode'],
                    'ErrorDescription' => urldecode($transaction['AcquirerMessage'])
                ];

                return $notAuthorizedTransaction;
            }
        }
    }

    protected function getCustomer($order)
    {
        if ($this->_customer) {
            return $this->_customer;
        }

        $this->_customer = Mage::getModel('customer/customer')->load($order->getCustomerId());

        return $this->_customer;
    }

    /**
     * Get customer cpf/cnpj
     * @param $data
     * @param $order
     * @return mixed|null
     */
    private function getCustomerDocumentNumber($data, $order)
    {
        $customerDocumentNumber = null;

        if (isset($data['TaxDocumentNumber'])) {
            $customerDocumentNumber = $data['TaxDocumentNumber'];
        } elseif (strlen($order->getCustomerTaxvat()) > 0) {
            $customerDocumentNumber = $order->getCustomerTaxvat();
        } elseif (strlen($this->getCustomer($order)->getData('cpf')) > 0) {
            $customerDocumentNumber = $this->getCustomer($order)->getData('cpf');
        }

        return $customerDocumentNumber;
    }

    /**
     * Prepare debit data to send to API
     * @param $order
     * @param $data
     * @param $standard
     * @return array
     */
    private function prepareDebitRequestData($order, $data, $standard)
    {
        $request["RequestKey"] = '00000000-0000-0000-0000-000000000000';
        $request["AmountInCents"] = $this->getAmountInCents($order->getBaseGrandTotal());
        $request['Bank'] = $this->getDebitServiceName($data['Bank']);
        $request['MerchantKey'] = $standard->getMerchantKey();
        // Buyer data
        $request["Buyer"] = array();
        $request["Buyer"] = $this->buyerDebitBillingData($order, $data, $request, $standard);
        // Order data
        $request['InstallmentCount'] = '0';
        $request["OrderKey"] = '00000000-0000-0000-0000-000000000000';
        $request["OrderRequest"]['AmountInCents'] = $this->getAmountInCents($order->getBaseGrandTotal());
        $request["OrderRequest"]['OrderReference'] = $order->getIncrementId();

        if ($standard->getEnvironment() != 'production') {
            $request["OrderRequest"]["OrderReference"] = md5(date('Y-m-d H:i:s')); // Identificação do pedido na loja
        }

        if ($standard->getEnvironment() != 'production') {
            $request['Bank'] .= 'Simulator';
        }

        $request['PaymentType'] = null;

        // Cart data
        $shoppingCart = $this->cartData($order, $data, $request, $standard);

        if (!is_array($shoppingCart)) {
            $shoppingCart = array();
        }

        $request["ShoppingCart"] = $shoppingCart[0];
        $deliveryAddress = $request['ShoppingCart']['DeliveryAddress'];

        unset($request['ShoppingCart']['DeliveryAddress']);

        $request['DeliveryAddress'] = $deliveryAddress;

        $request['ShoppingCart']['ShoppingCartItemCollection'][0]['DiscountAmountInCents'] =
            abs(round($order->getBaseDiscountAmount(), 2) * 100) -
                abs(round($order->getShippingDiscountAmount(), 2) * 100);

        return $request;
    }

    /**
     * Return service name (bank) based on code
     * @param string $code
     * @return string
     */
    private function getDebitServiceName($code)
    {
        $helper = new Uecommerce_Mundipagg_Model_Source_Debit();

        $services = $helper->getDebitServiceNames();

        return $services[$code];
    }

    /**
     * @param float $baseGrandTotal
     * @return int
     */
    private function getAmountInCents($baseGrandTotal)
    {
        $baseGrandTotal = str_replace(',', '.', $baseGrandTotal);

        return intval(strval(($baseGrandTotal * 100)));
    }

    /**
     * @param $url
     * @param $request
     * @param $merchantKey
     * @param $helperLog
     * @return mixed
     */
    private function sendDataToApi($url, $request, $merchantKey, $helperLog)
    {
        $helperLog->debug(print_r($url, true));
        $helperLog->debug(print_r($request, true));

        // Send payment data to MundiPagg
        $ch = curl_init();

        if (Mage::getStoreConfig('mundipagg_tests_cpf_cnpj') != '') {
            // If tests runinig
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        // Header
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array(
                'Content-Type:application/json',
                'Accept:application/json'
            )
        );

        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute post
        $response = curl_exec($ch);

        //check for curl errors
        $curlErrorNumber = curl_errno($ch);
        if ($curlErrorNumber !== 0) {
            $helperLog->error("CURL ERROR ". $curlErrorNumber . "!" . curl_error($ch));
        }

        // Close connection
        curl_close($ch);

        return $response;
    }

    /**
     * @param $response
     * @return mixed
     */
    private function responseFormatter($response)
    {
        return json_decode($response, true);
    }

    /**
     * @param $response
     * @return array|bool
     */
    private function debitResponseProcess($response)
    {
        // Error
        if (isset($response['ErrorReport']) && !empty($response['ErrorReport'])) {
            $_errorItemCollection = $response['ErrorReport']['ErrorItemCollection'];

            foreach ($_errorItemCollection as $errorItem) {
                $errorCode = $errorItem['ErrorCode'];
                $ErrorDescription = $errorItem['Description'];
            }

            return [
                'error'            => 1,
                'ErrorCode'        => $errorCode,
                'ErrorDescription' => Mage::helper('mundipagg')->__($ErrorDescription),
                'result'           => $response
            ];
        }

        // False
        if (isset($response['Success']) && (string)$response['Success'] == 'false') {
            return [
                'error'            => 1,
                'ErrorCode'        => 'WithError',
                'ErrorDescription' => 'WithError',
                'result'           => $response
            ];
        } else {
            // Success
            $result = [
                'success'              => true,
                'message'              => 4,
                'OrderKey'             => $response['OrderKey'],
                'TransactionKey'       => $response['TransactionKey'],
                'TransactionKeyToBank' => $response['TransactionKeyToBank'],
                'TransactionReference' => $response['TransactionReference'],
                'result'               => $response
            ];

            if (isset($response['CreateDate'])) {
                $result['CreateDate'] = $response['CreateDate'];
            }

            return $result;
        }

        return false;
    }

    /**
     * Decide what credit card operation will be used
     * @param $approvalRequest
     * @param $standard
     * @return string
     */
    private function getCreditCardOperation($approvalRequest, $standard)
    {
        if ($approvalRequest === 'partial') {
            return 'AuthOnly';
        }

        return $standard->getCreditCardOperationEnum();
    }

    private function processOrderStatus(
        $status,
        $order,
        $amountToCapture,
        $transactionKey,
        $orderReference,
        $returnMessageLabel,
        $capturedAmountInCents,
        $data,
        $transactionData,
        $statusWithError,
        $amountInCents = null
    ) {
        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $helperOrderStatus = Mage::helper('mundipagg/processOrderStatus');

        switch (strtolower($status)) {
            case 'captured':
                return $helperOrderStatus->captured($order, $amountToCapture, $transactionKey, $orderReference, $amountInCents);
            case 'paid':
            case 'overpaid':
                return $helperOrderStatus->paidOverpaid($order, $returnMessageLabel, $capturedAmountInCents, $data, $status);
            case 'underpaid':
                return $helperOrderStatus->underPaid($order, $helperLog, $returnMessageLabel, $capturedAmountInCents, $status);
            case 'notauthorized':
                $helper = Mage::helper('mundipagg');
                $grandTotal = $order->getGrandTotal();
                $grandTotalInCents = $helper->formatPriceToCents($grandTotal);
                $amountInCents = $transactionData['AmountInCents'];

                // if not authorized amount equal to order grand total, order must be canceled
                if (sprintf($amountInCents) != sprintf($grandTotalInCents)) {
                    $returnMessage = "OK | {$returnMessageLabel} | Order grand_total not equal to transaction AmountInCents";
                    $helperLog->info($returnMessage);
                    $helperLog->info("Current order status: " . $order->getStatusLabel());

                    return $returnMessage;
                }

                try {
                    // set flag to prevent send back a cancelation to Mundi via API
                    $this->setCanceledByNotificationFlag($order, true);
                    $this->tryCancelOrder($order);
                } catch (Exception $e) {
                    $returnMessage = "OK | {$returnMessageLabel} | {$e->getMessage()}";
                    $helperLog->info($returnMessage);
                    $helperLog->info("Current order status: " . $order->getStatusLabel());

                    return $returnMessage;
                }

                $returnMessage = "OK | {$returnMessageLabel} | Order canceled: total amount not authorized";
                $helperLog->info($returnMessage);
                $helperLog->info("Current order status: " . $order->getStatusLabel());

                return $returnMessage;
                break;
            case 'canceled':
            case 'refunded':
            case 'voided':
                if ($order->canUnhold()) {
                    $helperLog->info("{$returnMessageLabel} unholded.");

                    $order->unhold();
                }

                $success = false;
                $invoices = [];
                $canceledInvoices = [];

                foreach ($order->getInvoiceCollection() as $invoice) {
                    // We check if invoice can be refunded
                    if ($invoice->canRefund()) {
                        $invoices[] = $invoice;
                    }

                    // We check if invoice has already been canceled
                    if ($invoice->isCanceled()) {
                        $canceledInvoices[] = $invoice;
                    }
                }

                // Refund invoices and Credit Memo
                if (!empty($invoices) || !empty($canceledInvoices)) {
                    $service = Mage::getModel('sales/service_order', $order);

                    foreach ($invoices as $invoice) {
                        $this->closeInvoice($invoice);
                        $this->createCreditMemo($invoice, $service);
                    }

                    $this->closeOrder($order);
                    $success = true;
                }

                if (empty($invoices) && empty($canceledInvoices)) {
                    // Cancel order
                    $order->cancel()->save();

                    $helperLog->info("{$returnMessageLabel} | Order canceled.");
                    $helperLog->info("Current order status: " . $order->getStatusLabel());

                    // Return
                    $success = true;
                }

                if ($success) {
                    $returnMessage = "{$returnMessageLabel} | Order status '{$status}' processed.";
                    $helperLog->info($returnMessage);
                    $helperLog->info("Current order status: " . $order->getStatusLabel());

                    return "OK | {$returnMessage}";
                } else {
                    $returnMessage = "{$returnMessageLabel} | Unable to process transaction status '{$status}'.";
                    $helperLog->info($returnMessage);
                    $helperLog->info("Current order status: " . $order->getStatusLabel());

                    return "KO | {$returnMessage}";
                }

                break;
            case 'authorizedpendingcapture':
                $returnMessage = "OK | Order #{$order->getIncrementId()} | Transaction status '{$status}' received from post notification.";
                $helperLog->info($returnMessage);
                $helperLog->info("Current order status: " . $order->getStatusLabel());

                return $returnMessage;
                break;
            case $statusWithError:
                try {
                    Uecommerce_Mundipagg_Model_Standard::transactionWithError($order, false);
                    $returnMessage = "OK | {$returnMessageLabel} | Order changed to WithError status";
                } catch (Exception $e) {
                    $returnMessage = "KO | {$returnMessageLabel} | {$e->getMessage()}";
                }

                $helperLog->info($returnMessage);
                $helperLog->info("Current order status: " . $order->getStatusLabel());

                return $returnMessage;
                break;
            // For other status we add comment to history
            default:
                $returnMessage = "Order #{$order->getIncrementId()} | unexpected transaction status: {$status}";
                $helperLog->info($returnMessage);

                return "OK | {$returnMessage}";
        }
    }
}
