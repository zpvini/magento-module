<?php

class Uecommerce_Mundipagg_InstantBuyController extends Uecommerce_Mundipagg_Controller_Abstract {

	/**
	 * Bandeiras aceitas, as mesmas que constam no admin do Magento
	 */
	const CREDIT_CARD_BRAND_VISA       = 'Visa';
	const CREDIT_CARD_BRAND_MASTERCARD = 'Mastercard';
	const CREDIT_CARD_BRAND_HIPERCARD  = 'Hipercard';
	const CREDIT_CARD_BRAND_AMEX       = 'Amex';
	const CREDIT_CARD_BRAND_DINERS     = 'Diners';
	const CREDIT_CARD_BRAND_ELO        = 'Elo';

	const URL_SANDBOX    = 'https://sandbox.mundipaggone.com/CreditCard/';
	const URL_PRODUCTION = 'https://transactionv2.mundipaggone.com/CreditCard/';

	private $url;
	private $modelStandard;
	private $helperUtils;

	/**
	 * Uecommerce_Mundipagg_InstantBuyController constructor.
	 */
	public function _construct() {
		$environment = Mage::getStoreConfig('payment/mundipagg_standard/environment');
		$this->modelStandard = new Uecommerce_Mundipagg_Model_Standard();
		$this->helperUtils = new Uecommerce_Mundipagg_Helper_Util();

		if ($environment == 'development') {
			$this->url = self::URL_SANDBOX;
		} else {
			$this->url = self::URL_PRODUCTION;
		}
	}

	/**
	 * Cria o instantBuyKey na MundiPagg e retorna o JSON da API
	 * 
	 * Action espera um POST com os parametros abaixo
	 * 
	 * @TODO Gravar o instantBuyKey na tabela mundipagg_card_on_file
	 * 
	 * string CreditCardBrand (ver constantes desta classe)
	 * int CreditCardNumber
	 * int|string ExpMonth
	 * int|string ExpYear
	 * string HolderName
	 * boolean IsOneDollarAuthEnabled
	 * int SecurityCode
	 */
	public function createInstantBuyKeyAction() {
		$post = $this->getRequest()->getPost();
		$response = $this->createInstantBuy($post);

		echo $this->jsonResponse($response, true);

		return;
	}

	/**
	 * Carrega os tokens da tabela mundipagg_card_on_file e retorna em JSON
	 */
	public function getInstantBuyKeysAction() {
		$collection = Mage::getModel('mundipagg/cardonfile')->getCollection();
		$data = array();

		foreach ($collection as $i){
			$data[] = $i->getData();
		}

		echo $this->jsonResponse($data);
		return;
	}

	/**
	 * Espera um post com o campo 'instantBuyKey' e devolve um json com o retorno
	 * da API da Mundi 'MundiResponse' e da operação no BD do Magento 'MagentoResponse'
	 */
	public function deleteInstantBuyKeyAction() {
		$instantBuyKey = $this->getRequest()->getPost('instantBuyKey');
		$url = "{$this->url}/{$instantBuyKey}";

		// deleta instantBuyKey na Mundi via API
		$mundiResponse = $this->sendRequest(array(), $url, array(), "DELETE");
		$magentoResponse = array();
		$response = array();

		//se sucesso, deleta da tabela mundipagg_card_on_file
		if (isset($mundiResponse['Success'])) {
			if ($mundiResponse['Success'] == 'true') {
				$model = Mage::getModel('mundipagg/cardonfile');

				try {
					$cc = $model->loadByToken($instantBuyKey);
					$cc->delete();

					$magentoResponse = array(
						'Success' => true,
						'Message' => 'instant by removido da base com sucesso'
					);

				} catch (Exception $e) {
					$magentoResponse = array(
						'Success' => false,
						'Message' => 'nao foi possivel remover o instantBuyKey da base do Magento. Error'
					);
				}
			} else {
				$magentoResponse = false;
			}
		}

		$response['MundiResponse'] = $mundiResponse;

		if(!empty($magentoResponse)){
			$response['MagentoResponse'] = $magentoResponse;
		}

		echo $this->jsonResponse($response);

		return;
	}

	private function sendRequest($dataToPost, $url, $_logRequest = array(), $method = null) {
		$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
		$debug = $this->modelStandard->getDebug();

		if ($debug) {

			if (empty($_logRequest)) {
				$_logRequest = $dataToPost;
			}

			$requestRawJson = json_encode($dataToPost);
			$requestJSON = $this->helperUtils->jsonEncodePretty($_logRequest);

			$helperLog->debug("Request: {$requestJSON}\n");
		}

		$ch = curl_init();

		// Header
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'MerchantKey: ' . $this->modelStandard->getMerchantKey() . ''));
		// Set the url, number of POST vars, POST data
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestRawJson);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if (!is_null($method)) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		}

		// Execute post
		$_response = curl_exec($ch);

		// Close connection
		curl_close($ch);

		// Is there an error?
		$xml = simplexml_load_string($_response);
		$responseJSON = $this->helperUtils->jsonEncodePretty($xml);
		$responseArray = json_decode($responseJSON, true);

		if ($debug) {
			$helperLog->debug("Response: {$responseJSON} \n");
		}

		return $responseArray;
	}

	/**
	 * Cria o instantBuyKey na API da Mundi
	 * 
	 * @TODO Gravar o instantBuyKey na tabela mundipagg_card_on_file
	 * 
	 * @param $post
	 * @return array
	 */
	private function createInstantBuy($post) {
		$oneDollarAuth = $this->checkArrayIndex($post, 'IsOneDollarAuthEnabled');

		if (is_null($oneDollarAuth)) {
			$oneDollarAuth = false;
		}

		$dataToPost = array();
		$dataToPost['CreditCardBrand'] = $this->checkArrayIndex($post, 'CreditCardBrand');
		$dataToPost['CreditCardNumber'] = $this->checkArrayIndex($post, 'CreditCardNumber');
		$dataToPost['ExpMonth'] = $this->checkArrayIndex($post, 'ExpMonth');
		$dataToPost['ExpYear'] = $this->checkArrayIndex($post, 'ExpYear');
		$dataToPost['HolderName'] = $this->checkArrayIndex($post, 'HolderName');
		$dataToPost['IsOneDollarAuthEnabled'] = $oneDollarAuth;
		$dataToPost['SecurityCode'] = $this->checkArrayIndex($post, 'SecurityCode');
		$response = array();
		$errors = array();
		$data = null;

		foreach ($dataToPost as $key => $i) {
			if ($key != 'IsOneDollarAuthEnabled') {
				$i = trim($i);

				if (is_null($i) || empty($i)) {
					$errors[] = "{$key} is required";
				}
			}
		}

		$environment = Mage::getStoreConfig('payment/mundipagg_standard/environment');

		if ($environment == 'development') {
			$url = self::URL_SANDBOX;
		} else {
			$url = self::URL_PRODUCTION;
		}

		$hasErrors = empty($errors) ? false : true;

		//composing response
		if (!empty($errors)) {
			$response['hasErrors'] = $hasErrors;
			$response['errors'] = $errors;

		} else {
			$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
			$responseOne = $this->sendRequest($dataToPost, $url);
			$responseOne = $responseOne;
			$logRequest = $dataToPost;
			$logResponse = $responseOne;
			$errorReport = $this->checkArrayIndex($responseOne, 'ErrorReport');

			//obfuscate customer data
			$logRequest['CreditCardNumber'] = $this->obfuscateData($dataToPost['CreditCardNumber']);
			$logRequest['ExpMonth'] = $this->obfuscateData($dataToPost['ExpMonth']);
			$logRequest['ExpYear'] = $this->obfuscateData($dataToPost['ExpYear']);
			$logRequest['SecurityCode'] = $this->obfuscateData($dataToPost['SecurityCode']);

			$helperLog->debug("Request: {$this->helperUtils->jsonEncodePretty($logRequest)}");

			if (!is_null($errorReport) && count($errorReport) > 0) {
				$response['HasError'] = true;
			} else {
				$response['HasError'] = false;
				$logResponse['MerchantKey'] = $this->obfuscateData($logResponse['MerchantKey']);
				$logResponse['InstantBuyKey'] = $this->obfuscateData($logResponse['InstantBuyKey']);
			}

			$helperLog->debug("Response: {$this->helperUtils->jsonEncodePretty($logResponse)}");

			$response['MundiPaggResponse'] = $responseOne;
		}

		return $response;
	}

	private function checkArrayIndex($array, $index) {
		$data = isset($array[$index]) ? $array[$index] : null;

		return $data;
	}

	private function obfuscateData($str) {
		$newStr = preg_replace('/[0-9a-zA-z]+/', "xxx", $str);

		return $newStr;
	}

}