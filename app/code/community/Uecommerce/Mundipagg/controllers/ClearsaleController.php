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

	public function getSessionIdAction() {
		var_dump($this->getSessionId());
		var_dump(strlen($this->getSessionId()));
	}

	private function getSessionId() {
		$session = Mage::getSingleton('customer/session');
//		$session->unsetData('session_id');
		$sessionId = $session->getData(Uecommerce_Mundipagg_Model_Customer_Session::SESSION_ID);

		if (is_null($sessionId) || $sessionId == false || empty($sessionId)) {
			$sessionId = $this->generateSessionId();
		}

		return $sessionId;
	}

	private function generateSessionId() {
		$session = Mage::getSingleton('customer/session');
		$sessionId = $this->generateGuid();

		$session->setData(Uecommerce_Mundipagg_Model_Customer_Session::SESSION_ID, $sessionId);

		return $sessionId;
	}

	private function generateGuid() {
		$guid = uniqid('', true);

//		if (function_exists('com_create_guid') === true) {
//			$guid = trim(com_create_guid(), '{}');
//		} else {
//			$guid = sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
//		}

		$guid = str_replace('.', '-', $guid);
		$guid = substr($guid, 0, 20);

		return $guid;
	}

}