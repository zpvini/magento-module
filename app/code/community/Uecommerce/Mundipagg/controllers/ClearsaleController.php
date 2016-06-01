<?php

class Uecommerce_Mundipagg_ClearsaleController extends Uecommerce_Mundipagg_Controller_Abstract {

	public function getConfigAction() {

		if ($this->requestIsValid() == false) {
			$this->jsonResponse($this->getResponseForInvalidRequest());

			return false;
		}

		$entityId = Mage::getStoreConfig('payment/mundipagg_standard/clearsale_entityid');
		$app = Mage::getStoreConfig('payment/mundipagg_standard/clearsale_app');
		$response = array(
			'entityId'  => $entityId,
			'app'       => $app,
			'sessionId' => $this->getSessionId()
		);

		try {
			return $this->jsonResponse($response);

		} catch (Exception $e) {

		}
	}

	private function getSessionId() {
		$session = Mage::getSingleton('customer/session');
		$sessionId = $session->getData(Uecommerce_Mundipagg_Model_Customer_Session::SESSION_ID);

		if (is_null($sessionId) || $sessionId == false || empty($sessionId)) {
			$sessionId = uniqid('mund19-');
			$session->setData(Uecommerce_Mundipagg_Model_Customer_Session::SESSION_ID, $sessionId);
		}

		return $sessionId;
	}

}