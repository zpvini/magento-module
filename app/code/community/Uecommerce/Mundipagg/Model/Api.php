<?php
/**
 * Uecommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Uecommerce EULA.
 * It is also available through the world-wide-web at this URL:
 * http://www.uecommerce.com.br/
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the extension
 * to newer versions in the future. If you wish to customize the extension
 * for your needs please refer to http://www.uecommerce.com.br/ for more information
 *
 * @category   Uecommerce
 * @package    Uecommerce_Mundipagg
 * @copyright  Copyright (c) 2012 Uecommerce (http://www.uecommerce.com.br/)
 * @license    http://www.uecommerce.com.br/
 */

/**
 * Mundipagg Payment module
 *
 * @category   Uecommerce
 * @package    Uecommerce_Mundipagg
 * @author     Uecommerce Dev Team
 */
class Uecommerce_Mundipagg_Model_Api extends Uecommerce_Mundipagg_Model_Standard {

	private $helperUtil;
	private $modelStandard;
	private $debugEnabled;
	private $moduleVersion;

	public function __construct() {
		$this->helperUtil = new Uecommerce_Mundipagg_Helper_Util();
		$this->modelStandard = new Uecommerce_Mundipagg_Model_Standard();
		$this->moduleVersion = Mage::helper('mundipagg')->getExtensionVersion();
		$this->debugEnabled = $this->modelStandard->getDebug();
		parent::_construct();
	}

	/**
	 * Credit Card Transaction
	 */
	public function creditCardTransaction($order, $data, Uecommerce_Mundipagg_Model_Standard $standard) {
		$_logRequest = array();

		try {
			// Installments configuration
			$installment = $standard->getParcelamento();
			$qtdParcelasMax = $standard->getParcelamentoMax();

			// Get Webservice URL
			$url = $standard->getURL();

			// Set Data
			$_request = array();
			$_request["Order"] = array();
			$_request["Order"]["OrderReference"] = $order->getIncrementId();

//			if ($standard->getEnvironment() != 'production') {
//				$_request["Order"]["OrderReference"] = md5(date('Y-m-d H:i:s')); // Identificação do pedido na loja
//			}

			/*
			* Append transaction (multi credit card payments)
			* When one of Credit Cards has not been authorized and we try with a new one)
			*/
			if ($orderReference = $order->getPayment()->getAdditionalInformation('OrderReference')) {
				$_request["Order"]["OrderReference"] = $orderReference;
			}

			// Collection
			$_request["CreditCardTransactionCollection"] = array();

			/* @var $recurrencyModel Uecommerce_Mundipagg_Model_Recurrency */
			$recurrencyModel = Mage::getModel('mundipagg/recurrency');

			$creditcardTransactionCollection = array();

			// Partial Payment (we use this reference in order to authorize the rest of the amount)
			if ($order->getPayment()->getAdditionalInformation('OrderReference')) {
				$_request["CreditCardTransactionCollection"]["OrderReference"] = $order->getPayment()->getAdditionalInformation('OrderReference');
			}

			$baseGrandTotal = str_replace(',', '.', $order->getBaseGrandTotal());
			$amountInCentsVar = intval(strval(($baseGrandTotal * 100)));

			// CreditCardOperationEnum : if more than one payment method we use AuthOnly and then capture if all are ok
			$helper = Mage::helper('mundipagg');

			$num = $helper->getCreditCardsNumber($data['payment_method']);

			$installmentCount = 1;

			if ($num > 1) {
				$creditCardOperationEnum = 'AuthOnly';
			} else {
				$creditCardOperationEnum = $standard->getCreditCardOperationEnum();
			}

			foreach ($data['payment'] as $i => $paymentData) {
				$creditcardTransactionData = new stdclass();
				$creditcardTransactionData->CreditCard = new stdclass();
				$creditcardTransactionData->Options = new stdclass();

				// InstantBuyKey payment
				if (isset($paymentData['card_on_file_id'])) {
					$token = Mage::getModel('mundipagg/cardonfile')->load($paymentData['card_on_file_id']);

					if ($token->getId() && $token->getEntityId() == $order->getCustomerId()) {
						$creditcardTransactionData->CreditCard->InstantBuyKey = $token->getToken();
						$creditcardTransactionData->CreditCard->CreditCardBrand = $token->getCcType();
						$creditcardTransactionData->CreditCardOperation = $creditCardOperationEnum;
						/** Tipo de operação: AuthOnly | AuthAndCapture | AuthAndCaptureWithDelay  */
						$creditcardTransactionData->AmountInCents = intval(strval(($paymentData['AmountInCents']))); // Valor da transação
						$creditcardTransactionData->InstallmentCount = $paymentData['InstallmentCount']; // Nº de parcelas
						$creditcardTransactionData->Options->CurrencyIso = "BRL"; //Moeda do pedido
					}

				} else { // Credit Card
					$creditcardTransactionData->CreditCard->CreditCardNumber = $paymentData['CreditCardNumber']; // Número do cartão 
					$creditcardTransactionData->CreditCard->HolderName = $paymentData['HolderName']; // Nome do cartão
					$creditcardTransactionData->CreditCard->SecurityCode = $paymentData['SecurityCode']; // Código de segurança
					$creditcardTransactionData->CreditCard->ExpMonth = $paymentData['ExpMonth']; // Mês Exp
					$creditcardTransactionData->CreditCard->ExpYear = $paymentData['ExpYear']; // Ano Exp 
					$creditcardTransactionData->CreditCard->CreditCardBrand = $paymentData['CreditCardBrandEnum']; // Bandeira do cartão : Visa ,MasterCard ,Hipercard ,Amex */
					$creditcardTransactionData->CreditCardOperation = $creditCardOperationEnum;
					/** Tipo de operação: AuthOnly | AuthAndCapture | AuthAndCaptureWithDelay  */
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

			$_request = $recurrencyModel->generateRecurrences($_request, $installmentCount);

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

			if ($standard->getDebug() == 1) {
				$_logRequest = $_request;

				foreach ($_request["CreditCardTransactionCollection"] as $key => $paymentData) {
					if (isset($_logRequest["CreditCardTransactionCollection"][$key]["CreditCard"]["CreditCardNumber"])) {
						$_logRequest["CreditCardTransactionCollection"][$key]["CreditCard"]["CreditCardNumber"] = 'xxxxxxxxxxxxxxxx';
					}

					if (isset($_logRequest["CreditCardTransactionCollection"][$key]["CreditCard"]["SecurityCode"])) {
						$_logRequest["CreditCardTransactionCollection"][$key]["CreditCard"]["SecurityCode"] = 'xxx';
					}

					if (isset($_logRequest["CreditCardTransactionCollection"][$key]["CreditCard"]["ExpMonth"])) {
						$_logRequest["CreditCardTransactionCollection"][$key]["CreditCard"]["ExpMonth"] = 'xx';
					}

					if (isset($_logRequest["CreditCardTransactionCollection"][$key]["CreditCard"]["ExpYear"])) {
						$_logRequest["CreditCardTransactionCollection"][$key]["CreditCard"]["ExpYear"] = 'xx';
					}
				}
			}

			// Data
			$_response = $this->sendRequest($_request, $url, $_logRequest);
			$xml = $_response['xmlData'];
			$dataR = $_response['arrayData'];

			// if some error ocurred ex.: http 500 internal server error
			if (isset($dataR['ErrorReport']) && !empty($dataR['ErrorReport'])) {
				$_errorItemCollection = $dataR['ErrorReport']['ErrorItemCollection'];

				// Return errors
				return array(
					'error'               => 1,
					'ErrorCode'           => '',
					'ErrorDescription'    => '',
					'OrderKey'            => isset($dataR['OrderResult']['OrderKey']) ? $dataR['OrderResult']['OrderKey'] : null,
					'OrderReference'      => isset($dataR['OrderResult']['OrderReference']) ? $dataR['OrderResult']['OrderReference'] : null,
					'ErrorItemCollection' => $_errorItemCollection,
					'result'              => $dataR,
				);
			}

			// Transactions colllection
			$creditCardTransactionResultCollection = $dataR['CreditCardTransactionResultCollection'];

			// Only 1 transaction
			if (count($xml->CreditCardTransactionResultCollection->CreditCardTransactionResult) == 1) {
				//and transaction success is true
				if ((string)$creditCardTransactionResultCollection['CreditCardTransactionResult']['Success'] == 'true') {
					$trans = $creditCardTransactionResultCollection['CreditCardTransactionResult'];

					// We save Card On File
					if ($data['customer_id'] != 0 && isset($data['payment'][1]['token']) && $data['payment'][1]['token'] == 'new') {
						$cardonfile = Mage::getModel('mundipagg/cardonfile');

						$cardonfile->setEntityId($data['customer_id']);
						$cardonfile->setAddressId($data['address_id']);
						$cardonfile->setCcType($data['payment'][1]['CreditCardBrandEnum']);
						$cardonfile->setCreditCardMask($trans['CreditCard']['MaskedCreditCardNumber']);
						$cardonfile->setExpiresAt(date("Y-m-t", mktime(0, 0, 0, $data['payment'][1]['ExpMonth'], 1, $data['payment'][1]['ExpYear'])));
						$cardonfile->setToken($trans['CreditCard']['InstantBuyKey']);
						$cardonfile->setActive(1);

						$cardonfile->save();
					}

					$result = array(
						'success'        => true,
						'message'        => 1,
						'returnMessage'  => urldecode($creditCardTransactionResultCollection['CreditCardTransactionResult']['AcquirerMessage']),
						'OrderKey'       => $dataR['OrderResult']['OrderKey'],
						'OrderReference' => $dataR['OrderResult']['OrderReference'],
						'isRecurrency'   => $recurrencyModel->recurrencyExists(),
						'result'         => $xml
					);

					if (isset($dataR['OrderResult']['CreateDate'])) {
						$result['CreateDate'] = $dataR['OrderResult']['CreateDate'];
					}

					return $result;

				} else {
					// CreditCardTransactionResult success == false, not authorized
					$result = array(
						'error'            => 1,
						'ErrorCode'        => $creditCardTransactionResultCollection['CreditCardTransactionResult']['AcquirerReturnCode'],
						'ErrorDescription' => urldecode($creditCardTransactionResultCollection['CreditCardTransactionResult']['AcquirerMessage']),
						'OrderKey'         => $dataR['OrderResult']['OrderKey'],
						'OrderReference'   => $dataR['OrderResult']['OrderReference'],
						'result'           => $xml
					);

					if (isset($dataR['OrderResult']['CreateDate'])) {
						$result['CreateDate'] = $dataR['OrderResult']['CreateDate'];
					}

					/**
					 * @TODO precisa refatorar isto, pois deste jeito esta gravando offlineretry pra que qualquer pedido
					 * com mais de 1 cartao
					 */
					// save offline retry statements if this feature is enabled
					$orderResult = $dataR['OrderResult'];
					$this->saveOfflineRetryStatements($orderResult['OrderReference'], new DateTime($orderResult['CreateDate']));

					return $result;
				}
			} else { // More than 1 transaction
				$allTransactions = $creditCardTransactionResultCollection['CreditCardTransactionResult'];

				// We remove other transactions made before
				$actualTransactions = count($data['payment']);
				$totalTransactions = count($creditCardTransactionResultCollection['CreditCardTransactionResult']);
				$transactionsToDelete = $totalTransactions - $actualTransactions;

				if ($totalTransactions > $actualTransactions) {
					for ($i = 0; $i <= ($transactionsToDelete - 1); $i++) {
						unset($allTransactions[$i]);
					}

					// Reorganize array indexes from 0
					$allTransactions = array_values($allTransactions);
				}

				$needSaveOfflineRetry = true;

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

//					//some transaction not authorized, save offline retry if necessary
//					if (isset($trans['Success']) && $trans['Success'] == 'false' && $needSaveOfflineRetry) {
//						$needSaveOfflineRetry = false;
//						$orderResult = $dataR['OrderResult'];
//
//						$this->saveOfflineRetryStatements($orderResult['OrderReference'], new DateTime($orderResult['CreateDate']));
//					}

				}

				// Result
				$result = array(
					'success'        => true,
					'message'        => 1,
					'OrderKey'       => $dataR['OrderResult']['OrderKey'],
					'OrderReference' => $dataR['OrderResult']['OrderReference'],
					'isRecurrency'   => $recurrencyModel->recurrencyExists(),
					'result'         => $xml,
				);

				if (isset($dataR['OrderResult']['CreateDate'])) {
					$result['CreateDate'] = $dataR['OrderResult']['CreateDate'];
				}

				return $result;
			}
		} catch (Exception $e) {
			//Redirect to Cancel page

			Mage::getSingleton('checkout/session')->setApprovalRequestSuccess('cancel');

			//Log error
			$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
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
	 * Convert CreditcardTransaction Collection From Request
	 */
	public function ConvertCreditcardTransactionCollectionFromRequest($creditcardTransactionCollectionRequest, $standard) {
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
	public function boletoTransaction($order, $data, Uecommerce_Mundipagg_Model_Standard $standard) {
		try {
			// Get Webservice URL
			$url = $standard->getURL();

			// Set Data
			$_request = array();
			$_request["Order"] = array();
			$_request["Order"]["OrderReference"] = $order->getIncrementId();

//			if ($standard->getEnvironment() != 'production') {
//				$_request["Order"]["OrderReference"] = md5(date('Y-m-d H:i:s')); // Identificação do pedido na loja
//			}

			$_request["BoletoTransactionCollection"] = array();

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

			// Data
			$_response = $this->sendRequest($_request, $url);

			$xml = $_response['xmlData'];
			$data = $_response['arrayData'];

			// Error
			if (isset($data['ErrorReport']) && !empty($data['ErrorReport'])) {
				$_errorItemCollection = $data['ErrorReport']['ErrorItemCollection'];

				foreach ($_errorItemCollection as $errorItem) {
					$errorCode = $errorItem['ErrorCode'];
					$ErrorDescription = $errorItem['Description'];
				}

				return array(
					'error'            => 1,
					'ErrorCode'        => $errorCode,
					'ErrorDescription' => Mage::helper('mundipagg')->__($ErrorDescription),
					'result'           => $data
				);
			}

			// False
			if (isset($data['Success']) && (string)$data['Success'] == 'false') {
				return array(
					'error'            => 1,
					'ErrorCode'        => 'WithError',
					'ErrorDescription' => 'WithError',
					'result'           => $data
				);
			} else {
				// Success
				$result = array(
					'success'        => true,
					'message'        => 0,
					'OrderKey'       => $data['OrderResult']['OrderKey'],
					'OrderReference' => $data['OrderResult']['OrderReference'],
					'result'         => $data
				);

				if (isset($data['OrderResult']['CreateDate'])) {
					$result['CreateDate'] = $data['OrderResult']['CreateDate'];
				}

				return $result;
			}
		} catch (Exception $e) {
			//Redirect to Cancel page
			Mage::getSingleton('checkout/session')->setApprovalRequestSuccess('cancel');

			//Log error
			$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
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
	public function ConvertBoletoTransactionCollectionFromRequest($boletoTransactionCollectionRequest) {
		$newBoletoTransCollection = array();
		$counter = 0;

		foreach ($boletoTransactionCollectionRequest as $boletoTransItem) {
			$boletoTrans = array();

			$boletoTrans["AmountInCents"] = $boletoTransItem->AmountInCents;
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
	 * Debit transaction
	 **/
	public function debitTransaction($order, $data, Uecommerce_Mundipagg_Model_Standard $standard) {
		$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

		try {
			// Get Webservice URL
			$url = $standard->getURL();

			$baseGrandTotal = str_replace(',', '.', $order->getBaseGrandTotal());
			$amountInCentsVar = intval(strval(($baseGrandTotal * 100)));

			// Set Data
			$_request = array();

			$_request["RequestKey"] = '00000000-0000-0000-0000-000000000000';
			$_request["AmountInCents"] = $amountInCentsVar;
			$_request['Bank'] = $data['Bank'];
			$_request['MerchantKey'] = $standard->getMerchantKey();

			// Buyer data
			$_request["Buyer"] = array();
			$_request["Buyer"] = $this->buyerDebitBillingData($order, $data, $_request, $standard);

			// Order data
			$_request['InstallmentCount'] = '0';
			$_request["OrderKey"] = '00000000-0000-0000-0000-000000000000';
			$_request["OrderRequest"]['AmountInCents'] = $amountInCentsVar;
			$_request["OrderRequest"]['OrderReference'] = $order->getIncrementId();

			if ($standard->getEnvironment() != 'production') {
				$_request["OrderRequest"]["OrderReference"] = md5(date('Y-m-d H:i:s')); // Identificação do pedido na loja
			}

			if ($standard->getEnvironment() != 'production') {
				$_request['PaymentMethod'] = 'CieloSimulator';
			}

			$_request['PaymentType'] = null;

			// Cart data
			$shoppingCart = $this->cartData($order, $data, $_request, $standard);
			if (!is_array($shoppingCart)) {
				$shoppingCart = array();
			}
			$_request["ShoppingCart"] = $shoppingCart[0];
			$deliveryAddress = $_request['ShoppingCart']['DeliveryAddress'];
			unset($_request['ShoppingCart']['DeliveryAddress']);
			$_request['DeliveryAddress'] = $deliveryAddress;
			$_request['ShoppingCart']['ShoppingCartItemCollection'][0]['DiscountAmountInCents'] = 0;

			// Data
			$dataToPost = json_encode($_request);

			if ($standard->getDebug() == 1) {
//				Mage::log('Uecommerce_Mundipagg: ' . Mage::helper('mundipagg')->getExtensionVersion(), null, 'Uecommerce_Mundipagg.log');
//				Mage::log(print_r($_request, 1), null, 'Uecommerce_Mundipagg.log');
				$helperLog->debug(print_r($_request, true));
			}

			// Send payment data to MundiPagg
			$ch = curl_init();

			if (Mage::getStoreConfig('mundipagg_tests_cpf_cnpj') != '') {
				// If tests runinig
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			}

			// Header
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'MerchantKey: ' . $standard->getMerchantKey() . ''));

			// Set the url, number of POST vars, POST data
			curl_setopt($ch, CURLOPT_URL, $url);

			curl_setopt($ch, CURLOPT_POSTFIELDS, $dataToPost);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			// Execute post
			$_response = curl_exec($ch);

			if (curl_errno($ch)) {
				$helperLog->info(curl_error($ch));
//				Mage::log(curl_error($ch), null, 'Uecommerce_Mundipagg.log');
			}

			// Close connection
			curl_close($ch);

			if ($standard->getDebug() == 1) {
//				Mage::log('Uecommerce_Mundipagg: ' . Mage::helper('mundipagg')->getExtensionVersion(), null, 'Uecommerce_Mundipagg.log');
//				Mage::log(print_r($_response, 1), null, 'Uecommerce_Mundipagg.log');
				$helperLog->debug(print_r($_response, true));
			}

			// Is there an error?
			$xml = simplexml_load_string($_response);
			$json = json_encode($xml);
			$data = array();
			$data = json_decode($json, true);

			if ($standard->getDebug() == 1) {
//				Mage::log('Uecommerce_Mundipagg: ' . Mage::helper('mundipagg')->getExtensionVersion(), null, 'Uecommerce_Mundipagg.log');
//				Mage::log(print_r($data, 1), null, 'Uecommerce_Mundipagg.log');
				$helperLog->debug(print_r($data, true));
			}

			// Error
			if (isset($data['ErrorReport']) && !empty($data['ErrorReport'])) {
				$_errorItemCollection = $data['ErrorReport']['ErrorItemCollection'];

				foreach ($_errorItemCollection as $errorItem) {
					$errorCode = $errorItem['ErrorCode'];
					$ErrorDescription = $errorItem['Description'];
				}

				return array(
					'error'            => 1,
					'ErrorCode'        => $errorCode,
					'ErrorDescription' => Mage::helper('mundipagg')->__($ErrorDescription),
					'result'           => $data
				);
			}

			// False
			if (isset($data['Success']) && (string)$data['Success'] == 'false') {
				return array(
					'error'            => 1,
					'ErrorCode'        => 'WithError',
					'ErrorDescription' => 'WithError',
					'result'           => $data
				);
			} else {
				// Success
				$result = array(
					'success'              => true,
					'message'              => 4,
					'OrderKey'             => $data['OrderKey'],
					'TransactionKey'       => $data['TransactionKey'],
					'TransactionKeyToBank' => $data['TransactionKeyToBank'],
					'TransactionReference' => $data['TransactionReference'],
					'result'               => $data
				);

				if (isset($data['CreateDate'])) {
					$result['CreateDate'] = $data['CreateDate'];
				}

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
	 * Set buyer data
	 */
	public function buyerBillingData($order, $data, $_request, $standard) {
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

		// In case we doesn't have CPF or CNPJ informed we set default value for MundiPagg (required field)
		$data['DocumentNumber'] = isset($data['TaxDocumentNumber']) ? $data['TaxDocumentNumber'] : $order->getCustomerTaxvat();

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
		$address = array();
		$address['AddressType'] = 'Residential';
		$address['City'] = $billingAddress->getCity();
		$address['District'] = isset($street[3]) ? $street[3] : 'xxx';
		$address['Complement'] = isset($street[2]) ? $street[2] : '';
		$address['Number'] = isset($street[1]) ? $street[1] : '0';
		$address['State'] = $regionCode;
		$address['Street'] = isset($street[0]) ? $street[0] : 'xxx';
		$address['ZipCode'] = preg_replace('[\D]', '', $billingAddress->getPostcode());
		$address['Country'] = 'Brazil';

		$_request["Buyer"]["AddressCollection"] = array();
		$_request["Buyer"]["AddressCollection"] = array($address);

		return $_request["Buyer"];
	}

	/**
	 * Set buyer data
	 */
	public function buyerDebitBillingData($order, $data, $_request, $standard) {
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
		$regionCode = $billingAddress->getRegionCode();

		if ($billingAddress->getRegionCode() == '') {
			$regionCode = 'RJ';
		}

		$telephone = Mage::helper('mundipagg')->applyTelephoneMask($billingAddress->getTelephone());

		if ($billingAddress->getTelephone() == '') {
			$telephone = '55(21)88888888';
		}

		$testCpfCnpj = Mage::getStoreConfig('mundipagg_tests_cpf_cnpj');
		if ($testCpfCnpj != '') {
			$data['TaxDocumentNumber'] = $testCpfCnpj;
		}

		// In case we doesn't have CPF or CNPJ informed we set default value for MundiPagg (required field)
		$data['DocumentNumber'] = isset($data['TaxDocumentNumber']) ? $data['TaxDocumentNumber'] : $order->getCustomerTaxvat();

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
		$address = array();
		$address['AddressTypeEnum'] = 'Residential';
		$address['City'] = $billingAddress->getCity();
		$address['District'] = isset($street[3]) ? $street[3] : 'xxx';
		$address['Complement'] = isset($street[2]) ? $street[2] : '';
		$address['Number'] = isset($street[1]) ? $street[1] : '0';
		$address['State'] = $regionCode;
		$address['Street'] = isset($street[0]) ? $street[0] : 'xxx';
		$address['ZipCode'] = preg_replace('[\D]', '', $billingAddress->getPostcode());

		$_request["Buyer"]["BuyerAddressCollection"] = array();
		$_request["Buyer"]["BuyerAddressCollection"] = array($address);

		return $_request["Buyer"];
	}

	/**
	 * Set cart data
	 */
	public function cartData($order, $data, $_request, $standard) {
		$baseGrandTotal = round($order->getBaseGrandTotal(), 2);
		$baseDiscountAmount = round($order->getBaseDiscountAmount(), 2);

		if (abs($order->getBaseDiscountAmount()) > 0) {
			$totalWithoutDiscount = $baseGrandTotal + abs($baseDiscountAmount);

			$discount = round(($baseGrandTotal / $totalWithoutDiscount), 4);
		} else {
			$discount = 1;
		}

		$shippingDiscountAmount = round($order->getShippingDiscountAmount(), 2);

		if (abs($shippingDiscountAmount) > 0) {
			$totalShippingWithoutDiscount = round($order->getBaseShippingInclTax(), 2);
			$totalShippingWithDiscount = $totalShippingWithoutDiscount - abs($shippingDiscountAmount);

			$shippingDiscount = round(($totalShippingWithDiscount / $totalShippingWithoutDiscount), 4);
		} else {
			$shippingDiscount = 1;
		}

		$items = array();

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

		$address = array();
		$address['City'] = $addy->getCity();
		$address['District'] = isset($street[3]) ? $street[3] : 'xxx';
		$address['Complement'] = isset($street[2]) ? $street[2] : '';
		$address['Number'] = isset($street[1]) ? $street[1] : '0';
		$address['State'] = $regionCode;
		$address['Street'] = isset($street[0]) ? $street[0] : 'xxx';
		$address['ZipCode'] = preg_replace('[\D]', '', $addy->getPostcode());
		$address['Country'] = 'Brazil';
		$address['AddressType'] = "Shipping";

		$_request["ShoppingCartCollection"]["DeliveryAddress"] = array();

		$_request["ShoppingCartCollection"]["DeliveryAddress"] = $address;

		return array($_request["ShoppingCartCollection"]);
	}

	/**
	 * Manage Order Request: capture / void / refund
	 **/
	public function manageOrderRequest($data, Uecommerce_Mundipagg_Model_Standard $standard) {
		$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

		try {
			// Get Webservice URL
			$url = $standard->getURL() . '/' . $data['ManageOrderOperationEnum'];

			unset($data['ManageOrderOperationEnum']);

			// Get store key
			$key = $standard->getMerchantKey();

			$dataToPost = json_encode($data);

			if ($standard->getDebug() == 1) {
				$helperUtil = new Uecommerce_Mundipagg_Helper_Util();

				$helperLog->debug("Request:\n{$helperUtil->jsonEncodePretty($data)}\n");
			}

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

			// Close connection
			curl_close($ch);

			if ($standard->getDebug() == 1) {
				$xml = simplexml_load_string($_response);
				$domXml = new DOMDocument('1.0');

				$domXml->formatOutput = true;
				$domXml->loadXML($xml->asXML());

				$xml = $domXml->saveXML();

				$helperLog->debug("Response:\n{$xml}\n");
			}

			// Return
			return array('result' => simplexml_load_string($_response));

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
	 * Process order
	 * @param $order
	 * @param $data
	 */
	public function processOrder($postData) {
		$standard = Mage::getModel('mundipagg/standard');
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

			if ($standard->getConfigData('debug') == 1) {
				$orderReference = isset($xml->OrderReference) ? $xml->OrderReference : null;

				if (is_null($orderReference)) {
					$logMessage = "Notification post:\n{$xmlStatusNotificationString}\n";

				} else {
					$logMessage = "Notification post for order #{$orderReference}:\n{$xmlStatusNotificationString}";
				}

				$helperLog->debug($logMessage);
			}

			$orderReference = $data['OrderReference'];
			$order = Mage::getModel('sales/order');

			$order->loadByIncrementId($orderReference);

			if (!$order->getId()) {
				$returnMessage = "OrderReference don't correspond to a store order.";

				$helperLog->info("OrderReference: {$orderReference} | {$returnMessage}");

				return "Error | {$returnMessage}";
			}

			if (isset($data['OrderStatus'])) {
				$orderStatus = $data['OrderStatus'];

				//if MundiPagg order status is canceled, cancel the order on Magento
				if ($orderStatus == Uecommerce_Mundipagg_Model_Enum_OrderStatusEnum::CANCELED) {
					$returnMessageLabel = "Order #{$order->getIncrementId()}";

					if ($order->getState() == Mage_Sales_Model_Order::STATE_CANCELED) {
						$returnMessage = "OK | {$returnMessageLabel} | Order already canceled.";

						$helperLog->info($returnMessage);

						return $returnMessage;
					}

					try {
						$this->tryCancelOrder($order, "Canceled after MundiPagg notification post.");
						$returnMessage = "OK | {$returnMessageLabel} | Canceled successfully";
						$helperLog->info($returnMessage);

					} catch (Exception $e) {
						$returnMessage = "Error | {$returnMessageLabel} | {$e->getMessage()}";
						$helperLog->error($returnMessage);
					}
				}

				return $returnMessage;
			}

			if (!empty($data['BoletoTransaction'])) {
				$status = $data['BoletoTransaction']['BoletoTransactionStatus'];
				$transactionKey = $data['BoletoTransaction']['TransactionKey'];
				$capturedAmountInCents = $data['BoletoTransaction']['AmountPaidInCents'];
			}

			if (!empty($data['CreditCardTransaction'])) {
				$transactionType = Uecommerce_Mundipagg_Model_Enum_TransactionTypeEnum::CREDIT_CARD;
				$status = $data['CreditCardTransaction']['CreditCardTransactionStatus'];
				$transactionKey = $data['CreditCardTransaction']['TransactionKey'];
				$capturedAmountInCents = $data['CreditCardTransaction']['CapturedAmountInCents'];
			}

			if (!empty($data['OnlineDebitTransaction'])) {
				$transactionType = Uecommerce_Mundipagg_Model_Enum_TransactionTypeEnum::DEBITO;
				$status = $data['OnlineDebitTransaction']['OnlineDebitTransactionStatus'];
				$transactionKey = $data['OnlineDebitTransaction']['TransactionKey'];
				$capturedAmountInCents = $data['OnlineDebitTransaction']['AmountPaidInCents'];
			}

			// We check if transactionKey exists in database
			$t = 0;

			$transactions = Mage::getModel('sales/order_payment_transaction')
				->getCollection()
				->addAttributeToFilter('order_id', array('eq' => $order->getEntityId()));

			foreach ($transactions as $key => $transaction) {
				$orderTransactionKey = $transaction->getAdditionalInformation('TransactionKey');

				// transactionKey found
				if ($orderTransactionKey == $transactionKey) {
					$t++;
					continue;
				}
			}

			// transactionKey has been found so we can proceed
			if ($t > 0) {
				/**
				 * @var $recurrence Uecommerce_Mundiapgg_Model_Recurrency
				 */
				$recurrence = Mage::getModel('mundipagg/recurrency');
				$recurrence->checkRecurrencesByOrder($order);

				$status = strtolower($status);

				switch ($status) {
					case 'captured':
					case 'paid':
					case 'overpaid':
						if ($order->canUnhold()) {
							$order->unhold();
						}

						if (!$order->canInvoice()) {
							return 'OK';
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

							// Can invoice only if total captured amount is equal to GrandTotal
							if (abs($order->getGrandTotal() - $baseTotalPaid) < $epsilon) {
								$result = $this->createInvoice($order, $data, $baseTotalPaid, $status);

								return $result;

							} else {
								$order->save();

								return 'OK';
							}
						}

						// Create invoice
						if ($order->canInvoice() && abs($capturedAmountInCents * 0.01 - $order->getGrandTotal()) < $epsilon) {
							$result = $this->createInvoice($order, $data, $order->getGrandTotal(), $status);

							return $result;
						}

						$returnMessage = "Order {$order->getIncrementId()} | Unable to create invoice for this order.";

						$helperLog->error($returnMessage);
						return "KO | {$returnMessage}";

						break;

					case 'underpaid':
						if ($order->canUnhold()) {
							$order->unhold();
						}

						$order->addStatusHistoryComment('Captured offline amount of R$' . $capturedAmountInCents * 0.01, false);
						$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, 'underpaid');
						$order->setBaseTotalPaid($capturedAmountInCents * 0.01);
						$order->setTotalPaid($capturedAmountInCents * 0.01);
						$order->save();

						return 'OK';
						break;

					case 'notauthorized':
						return 'OK';
						break;

					case 'canceled':
					case 'refunded':
					case 'voided':
						if ($order->canUnhold()) {
							$order->unhold();
						}

						$ok = 0;
						$invoices = array();
						$canceledInvoices = array();

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
						if (!empty($invoices)) {
							$service = Mage::getModel('sales/service_order', $order);

							foreach ($invoices as $invoice) {
								$invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_CANCELED);
								$invoice->save();

								$creditmemo = $service->prepareInvoiceCreditmemo($invoice);
								$creditmemo->setOfflineRequested(true);
								$creditmemo->register()->save();
							}

							// Close order
							$order->setData('state', 'closed');
							$order->setStatus('closed');
							$order->save();

							// Return
							$ok++;
						}

						// Credit Memo
						if (!empty($canceledInvoices)) {
							$service = Mage::getModel('sales/service_order', $order);

							foreach ($invoices as $invoice) {
								$creditmemo = $service->prepareInvoiceCreditmemo($invoice);
								$creditmemo->setOfflineRequested(true);
								$creditmemo->register()->save();
							}

							// Close order
							$order->setData('state', Mage_Sales_Model_Order::STATE_CLOSED);
							$order->setStatus(Mage_Sales_Model_Order::STATE_CLOSED);
							$order->save();

							// Return
							$ok++;
						}

						if (empty($invoices) && empty($canceledInvoices)) {
							// Cancel order
							$order->cancel()->save();

							// Return
							$ok++;
						}

						if ($ok > 0) {
							return 'OK';
						} else {
							$responseToMundiPagg = "Order #{$order->getIncrementId()} | Unable to cancel the order.";

							$helperLog->info($responseToMundiPagg);

							return "KO | {$responseToMundiPagg}";
						}

						break;

					// For other status we add comment to history
					default:
						$responseToMundiPagg = "Order #{$order->getIncrementId()} | unexpected order status.";

						$order->addStatusHistoryComment($status, false);
						$order->save();

						$helperLog->info($responseToMundiPagg);

						return "KO | {$responseToMundiPagg}";
				}
			} else {
				$responseToMundiPagg = "TransactionKey {$transactionKey} not found on database.";

				$helperLog->info($responseToMundiPagg);

				return "KO | {$responseToMundiPagg}";
			}

		} catch (Exception $e) {
			$responseToMundiPagg = "Internal server error | {$e->getCode()} - ErrMsg: {$e->getMessage()}";

			//Log error
			$helperLog->error($e, true);

			//Mail error
			$this->mailError(print_r($e->getMessage(), 1));

			return "KO | {$responseToMundiPagg}";
		}
	}

	/**
	 * @param Mage_Sales_Model_Order $order
	 * @param string                 $comment
	 * @return bool
	 * @throws RuntimeException
	 */
	public function tryCancelOrder(Mage_Sales_Model_Order $order, $comment = null) {
		if ($order->canCancel()) {
			try {
				$order->cancel();

				if (!is_null($comment) && is_string($comment)) {
					$order->addStatusHistoryComment($comment);
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
	 * @author Ruan Azevedo
	 * @since 2016-07-20
	 * $
	 * Status reference:
	 * http://docs.mundipagg.com/docs/enumera%C3%A7%C3%B5es
	 *
	 * @param array $postData
	 */
	private function processCreditCardTransactionNotification($postData) {
		$status = $postData['CreditCardTransaction']['CreditCardTransactionStatus'];
		$transactionKey = $postData['CreditCardTransaction']['TransactionKey'];
		$capturedAmountInCents = $postData['CreditCardTransaction']['CapturedAmountInCents'];
		$ccTransactionEnum = new Uecommerce_Mundipagg_Model_Enum_CreditCardTransactionStatusEnum();
		$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

		switch ($status) {
			case $ccTransactionEnum::AUTHORIZED_PENDING_CAPTURE:
				break;

			case $ccTransactionEnum::CAPTURED:
				break;

			case $ccTransactionEnum::PARTIAL_CAPTURE:
				break;

			case $ccTransactionEnum::NOT_AUTHORIZED:
				break;

			case $ccTransactionEnum::VOIDED:
				break;

			case $ccTransactionEnum::PENDING_VOID:
				break;

			case $ccTransactionEnum::PARTIAL_VOID:
				break;

			case $ccTransactionEnum::REFUNDED:
				break;

			case $ccTransactionEnum::PENDING_REFUND:
				break;

			case $ccTransactionEnum::PARTIAL_REFUNDED:
				break;

			case $ccTransactionEnum::WITH_ERROR:
				break;

			case $ccTransactionEnum::NOT_FOUND_ACQUIRER:
				break;

			case $ccTransactionEnum::PENDING_AUTHORIZE:
				break;

			case $ccTransactionEnum::INVALID:
				break;
		}
	}

	/**
	 * @author Ruan Azevedo
	 * @since 2016-07-20
	 * Status reference:
	 * http://docs.mundipagg.com/docs/enumera%C3%A7%C3%B5es
	 */
	private function processBoletoTransactionNotification() {
		$status = '';
		$boletoTransactionEnum = new Uecommerce_Mundipagg_Model_Enum_BoletoTransactionStatusEnum();

		switch ($status) {
			case $boletoTransactionEnum::GENERATED:
				break;

			case $boletoTransactionEnum::PAID:
				break;

			case $boletoTransactionEnum::UNDERPAID:
				break;

			case $boletoTransactionEnum::OVERPAID:
				break;
		}
	}

	/**
	 * Create invoice
	 */
	private function createInvoice($order, $data, $totalPaid, $status) {
		$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

		if (!$invoice->getTotalQty()) {
			$order->addStatusHistoryComment('Cannot create an invoice without products.', false);
			$order->save();

			return 'KO';
		}

		$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
		$invoice->register();
		$invoice->getOrder()->setCustomerNoteNotify(true);
		$invoice->getOrder()->setIsInProcess(true);
		$invoice->setCanVoidFlag(true);

		$transactionSave = Mage::getModel('core/resource_transaction')
			->addObject($invoice)
			->addObject($invoice->getOrder());
		$transactionSave->save();

		// Send invoice email if enabled
		if (Mage::helper('sales')->canSendNewInvoiceEmail($order->getStoreId())) {
			$invoice->sendEmail(true);
			$invoice->setEmailSent(true);
		}

		$order->setBaseTotalPaid($totalPaid);
		$order->addStatusHistoryComment('Captured offline', false);

		$payment = $order->getPayment();

		$payment->setAdditionalInformation('OrderStatusEnum', $data['OrderStatus']);

		if ($payment->getAdditionalInformation('PaymentMethod') == 'mundipagg_creditcard') {
			$payment->setAdditionalInformation('CreditCardTransactionStatusEnum', $data['CreditCardTransaction']['CreditCardTransactionStatus']);
		}

		if ($payment->getAdditionalInformation('PaymentMethod') == 'mundipagg_boleto') {
			$payment->setAdditionalInformation('BoletoTransactionStatusEnum', $data['BoletoTransaction']['BoletoTransactionStatus']);
		}

		if (isset($data['OnlineDebitTransaction']['BankPaymentDate'])) {
			$payment->setAdditionalInformation('BankPaymentDate', $data['OnlineDebitTransaction']['BankPaymentDate']);
		}

		if (isset($data['OnlineDebitTransaction']['BankName'])) {
			$payment->setAdditionalInformation('BankName', $data['OnlineDebitTransaction']['BankName']);
		}

		if (isset($data['OnlineDebitTransaction']['Signature'])) {
			$payment->setAdditionalInformation('Signature', $data['OnlineDebitTransaction']['Signature']);
		}

		if (isset($data['OnlineDebitTransaction']['TransactionIdentifier'])) {
			$payment->setAdditionalInformation('TransactionIdentifier', $data['OnlineDebitTransaction']['TransactionIdentifier']);
		}

		$payment->save();

		if ($status == 'OverPaid' || $status == 'Overpaid') {
			$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'overpaid');
		} else {
			$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
		}

		$order->save();

		return 'OK';
	}

	/**
	 * Search by orderkey
	 * @param string $orderKey
	 * @return array
	 */
	public function getTransactionHistory($orderKey) {
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

		// Close connection
		curl_close($ch);

		if ($standard->getDebug() == 1) {
//			Mage::log('Uecommerce_Mundipagg: ' . Mage::helper('mundipagg')->getExtensionVersion() . ' Notification (return url)', null, 'Uecommerce_Mundipagg.log');
//			Mage::log(print_r($_response, 1), null, 'Uecommerce_Mundipagg.log');
			$helperLog->debug(print_r($_response, true));
		}

		// Return
		return array('result' => simplexml_load_string($_response));
	}

	/**
	 * Mail error to Mage::getStoreConfig('trans_email/ident_custom1/email')
	 *
	 * @author Ruan Azevedo <razevedo@mundipagg.com>
	 * @since 31-05-2016
	 * @param string $message
	 */
	public function mailError($message = '') {
		$mail = new Zend_Mail();
		$fromName = Mage::getStoreConfig('trans_email/ident_sales/name');
		$fromEmail = Mage::getStoreConfig('trans_email/ident_sales/email');
		$toEmail = Mage::getStoreConfig('trans_email/ident_custom1/email');
		$toName = Mage::getStoreConfig('trans_email/ident_custom1/name');
		$bcc = array('ruan.azevedo@gmail.com', 'razevedo@mundipagg.com');
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
	private function getRequestDataNode() {
		$antifraud = Mage::getStoreConfig('payment/mundipagg_standard/antifraud');
		$antifraudProvider = Mage::getStoreConfig('payment/mundipagg_standard/antifraud_provider');
		$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
		$helperHttpCore = new Mage_Core_Helper_Http();
		$customerIp = $helperHttpCore->getRemoteAddr();
		$outputMsg = "";
		$sessionId = '';
		$error = false;

		$requestData = array(
			'IpAddress' => $customerIp,
			'SessionId' => ''
		);

		if ($this->debugEnabled) {
			$helperLog->debug("Checking antifraud config...");
		}

		if ($antifraud == false) {
			if ($this->debugEnabled) {
				$helperLog->debug("Antifraud disabled.");
			}

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
//			Mage::throwException($outputMsg);
		}

		if (is_null($sessionId)) {
			$sessionId = '';
		}

		$requestData['SessionId'] = $sessionId;

		$helperLog->info($outputMsg);

		return $requestData;
	}

	private function clearAntifraudDataFromSession() {
		$customerSession = Mage::getSingleton('customer/session');
		$customerSession->unsetData(Uecommerce_Mundipagg_Model_Customer_Session::SESSION_ID);
	}

	/**
	 * Method to unify the transactions requests and his logs
	 *
	 * @author Ruan Azevedo <razvedo@mundipagg.com>
	 * @since 05-24-2016
	 * @param array  $dataToPost
	 * @param string $url
	 * @param array  $_logRequest
	 * @return array $_response
	 */
	private function sendRequest($dataToPost, $url, $_logRequest = array()) {
		$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

		if (empty($dataToPost) || empty($url)) {
			$errMsg = __METHOD__ . "Exception: one or more arguments not informed to request";

			$helperLog->error($errMsg);
			throw new InvalidArgumentException($errMsg);
		}

		$debug = $this->modelStandard->getDebug();

		if ($debug) {

			if (empty($_logRequest)) {
				$_logRequest = $dataToPost;
			}

			$requestRawJson = json_encode($dataToPost);
			$requestJSON = $this->helperUtil->jsonEncodePretty($_logRequest);

			$helperLog->debug("Request: {$requestJSON}\n");
		}

		$ch = curl_init();

		// Header
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'MerchantKey: ' . $this->modelStandard->getMerchantKey() . ''));
		// Set the url, number of POST vars, POST data
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestRawJson);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Execute post
		$_response = curl_exec($ch);

		// Close connection
		curl_close($ch);

		// Is there an error?
		$xml = simplexml_load_string($_response);
		$responseJSON = $this->helperUtil->jsonEncodePretty($xml);
		$responseArray = json_decode($responseJSON, true);

		if ($debug) {
			$helperLog->debug("Response: {$responseJSON} \n");
		}

		$responseData = array(
			'xmlData'   => $xml,
			'arrayData' => $responseArray
		);

		$this->clearAntifraudDataFromSession();

		return $responseData;
	}

	/**
	 * Check if order is in offline retry time
	 *
	 * @author Ruan Azevedo <razevedo@mundipagg.com>
	 * @since 2016-06-20
	 * @param string $orderIncrementId
	 * @return boolean
	 */
	public function orderIsInOfflineRetry($orderIncrementId) {
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
	 * @since 2016-06-23
	 * @param string   $orderIncrementId
	 * @param DateTime $createDate
	 */
	private function saveOfflineRetryStatements($orderIncrementId, DateTime $createDate) {
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

}
