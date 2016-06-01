<?php

class Uecommerce_Mundipagg_FcontrolController extends Uecommerce_Mundipagg_Controller_Abstract {

	public function getOrderIdAction() {

		if ($this->requestIsValid() == false) {
			echo $this->getResponseForInvalidRequest();

			return false;
		}

		$quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
		$quote = Mage::getModel("sales/quote")->load($quoteId);

		$quote->reserveOrderId();

		$incrementId = $quote->getReservedOrderId();
		$response['orderId'] = $incrementId;

		return $this->jsonResponse($response);
	}

	public function getConfigAction() {

		if ($this->requestIsValid() == false) {
			echo $this->getResponseForInvalidRequest();

			return false;
		}

		$response = array();
		$response['sessionId'] = $this->getSessionId();
		$response['key'] = Mage::getStoreConfig('payment/mundipagg_standard/fcontrol_key');

		try {
			return $this->jsonResponse($response);
		} catch (Exception $e) {
		}

	}

	public function reportErrorAction() {
		if ($this->requestIsValid() == false) {
			echo $this->getResponseForInvalidRequest();
			return false;
		}

		$api = new Uecommerce_Mundipagg_Model_Api();
		$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
		$message = $this->getRequest()->getPost('message');

		try {
			$helperLog->error($message, true);
			$api->mailError($message);
			
		} catch (Exception $e) {
			
		}
	}

}