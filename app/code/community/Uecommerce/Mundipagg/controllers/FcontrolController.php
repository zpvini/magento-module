<?php

class Uecommerce_Mundipagg_FcontrolController extends Uecommerce_Mundipagg_Controller_Abstract
{

    /*
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
	*/

    const FINGERPRINT_URL_SANDBOX    = 'https://static.fcontrol.com.br/fingerprint/hmlg-fcontrol-ed.min.js';
    const FINGERPRINT_URL_PRODUCTION = 'https://static.fcontrol.com.br/fingerprint/fcontrol.min-ed.js';

    public function getConfigAction()
    {
        $environment = Uecommerce_Mundipagg_Model_Source_FControlEnvironment::getEnvironment();
        $configStrPrefix = 'payment/mundipagg_standard';

        if ($environment == Uecommerce_Mundipagg_Model_Source_FControlEnvironment::SANDBOX) {
            $configStrKey = "{$configStrPrefix}/fcontrol_key_sandbox";
        } else {
            $configStrKey = "{$configStrPrefix}/fcontrol_key_production";
        }

        if ($environment == Uecommerce_Mundipagg_Model_Source_FControlEnvironment::SANDBOX) {
            $url = self::FINGERPRINT_URL_SANDBOX;
        } else {
            $url = self::FINGERPRINT_URL_PRODUCTION;
        }

        $response = array();
        $response['sessionId'] = $this->getSessionId();
        $response['key'] = Mage::getStoreConfig($configStrKey);
        $response['scriptUrl'] = $url;

        try {
            return $this->jsonResponse($response);
        } catch (Exception $e) {
        }
    }

    public function reportErrorAction()
    {
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

    public function logFpAction()
    {
        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $helperUtil = new Uecommerce_Mundipagg_Helper_Util();
        $event = $this->getRequest()->getPost('event');
        $data = $this->getRequest()->getPost('data');
        $data = json_decode($data);
        $data = $helperUtil->jsonEncodePretty($data);
        $data = stripslashes($data);
        $message = "Fingerprint {$event}:\n{$data}\n";

        try {
            $helperLog->info($message);
        } catch (Exception $e) {
        }
    }
}
