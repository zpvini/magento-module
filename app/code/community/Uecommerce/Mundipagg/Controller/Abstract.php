<?php

class Uecommerce_Mundipagg_Controller_Abstract extends Mage_Core_Controller_Front_Action {

	protected function jsonResponse($responseArray) {
		$json = json_encode($responseArray);

		$this->getResponse()->setHeader('Content-type', 'application/json');
		$this->getResponse()->setBody($json);
	}

	protected function requestIsValid() {
		$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
		$serverHost = $_SERVER['HTTP_HOST'];

		//getting request origin
		$requestServer = $this->getRequest()->getServer();
		$requestServerName = $requestServer['SERVER_NAME'];

		$helperLog->info("Checking request origin '{$requestServerName}'");

		//validating if the request is from the store
		if ($requestServerName == $serverHost) {
			return true;

		} else {
			$logMessage = "[SecurityAlert] Someone have tried to get data from a controller outside of the server.";

			$helperLog->warning($logMessage);
			$helperLog->warning(print_r($requestServer, true));

			return false;
		}
	}

	/**
	 * @return array
	 */
	protected function getResponseForInvalidRequest() {
		return array(
			'success' => false,
			'message' => "Bad guy... Go away, we have data about you."
		);
	}

	public function reportErrorAction() {

		try {
			$message = $this->getRequest()->getPost('errorMessage');
			$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
			$api = new Uecommerce_Mundipagg_Model_Api();

			$helperLog->error($message);
			$api->mailError($message);

		} catch (Exception $e) {
		}
	}

}