<?php

class Uecommerce_Mundipagg_ClearsaleController extends Uecommerce_Mundipagg_Controller_Abstract {

	public function getConfigAction() {

		if ($this->requestIsValid() == false) {
			echo $this->getResponseForInvalidRequest();
			return false;
		}

		$entityCode = Mage::getStoreConfig('payment/mundipagg_standard/clearsale_entitycode');
		$app = Mage::getStoreConfig('payment/mundipagg_standard/clearsale_app');
		$response = array(
			'entityCode'  => $entityCode,
			'app'       => $app,
			'sessionId' => $this->getSessionId()
		);

		try {
			return $this->jsonResponse($response);

		} catch (Exception $e) {

		}
	}

	private function getSessionId() {
		$sessionId = Uecommerce_Mundipagg_Model_Customer_Session::getSessionId();

		if (is_null($sessionId) || $sessionId == false || empty($sessionId)) {
			$sessionId = uniqid('mund19-');
			Uecommerce_Mundipagg_Model_Customer_Session::setSessionId($sessionId);
		}

		return $sessionId;
	}

}