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

	public function sessionAction() {

		if ($this->requestIsValid() == false) {
			echo $this->getResponseForInvalidRequest();
			return false;
		}

		$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
		$deviceId = $this->getRequest()->getPost('deviceId');

		if (is_null($deviceId) || empty($deviceId)) {
			$message = 'deviceId not informed';
			$response = array(
				'sucess'  => false,
				'message' => $message
			);

			$helperLog->error($message);
			return $this->jsonResponse($response);
		}

		try {
			$message = 'deviceId saved';
			$response = array(
				'success' => true,
				'message' => $message
			);

			$helperLog->info($message);
			Uecommerce_Mundipagg_Model_Customer_Session::setSessionId($deviceId);

		} catch (Exception $e) {
			$errMsg = "Impossible to save sessionId in customer session.";
			$response = array(
				'success' => false,
				'message' => $errMsg
			);

			$helperLog->error("{$errMsg} Error: {$e->getMessage()}");
		}

		return $this->jsonResponse($response);
	}

}