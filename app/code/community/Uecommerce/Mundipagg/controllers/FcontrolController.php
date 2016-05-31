<?php

class Uecommerce_Mundipagg_FcontrolController extends Mage_Core_Controller_Front_Action {

	public function getOrderIdAction() {
		$quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
		$quote = Mage::getModel("sales/quote")->load($quoteId);

		$quote->reserveOrderId();

		$incrementId = $quote->getReservedOrderId();
		$response['orderId'] = $incrementId;

		$this->jsonResponse($response);
		return;
	}

	public function sessionAction() {
		$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
		$session = Mage::getSingleton('customer/session');
		$param = $this->getRequest()->getPost('deviceId');
		$serverHost = $_SERVER['HTTP_HOST'];

		//getting request origin
		$requestServer = $this->getRequest()->getServer();
		$requestServerName = $requestServer['SERVER_NAME'];

		$helperLog->info("Checking request origin '{$requestServerName}'");

		//validating if the request is from the store
		if ($requestServerName != $serverHost) {
			$message = "Bad guy... Go away, we have data about you.";
			$response['success'] = false;
			$response['message'] = $message;

			$helperLog->warning("[SecurityAlert] Someone have tried to forge a deviceId for a session. Request origin data:");
			$helperLog->warning(print_r($requestServer, true));

			$this->jsonResponse($response);

			return;
		}

		$helperLog->info("Request origin is valid.");

		if (is_null($param) || empty($param)) {
			$message = 'deviceId not informed';
			$response = array(
				'sucess'  => false,
				'message' => $message
			);

			$helperLog->error($message);
			$this->jsonResponse($response);

			return;
		}

		try {
			$message = 'deviceId saved';
			$response = array(
				'success' => true,
				'message' => $message
			);

			$helperLog->info($message);
			$session->setData('device_id', $param);

		} catch (Exception $e) {
			$errMsg = "Impossible to save deviceId in customer session.";
			$response = array(
				'success' => false,
				'message' => $errMsg
			);

			$helperLog->error("{$errMsg} Error: {$e->getMessage()}");
		}

		$this->jsonResponse($response);
	}

	private function jsonResponse($responseArray) {
		$json = json_encode($responseArray);

		$this->getResponse()->setHeader('Content-type', 'application/json');
		$this->getResponse()->setBody($json);
	}

}