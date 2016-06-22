<?php

class Uecommerce_Mundipagg_Model_Offlineretry extends Mage_Core_Model_Abstract {

	public function _construct() {
		parent::_construct();
		$this->_init('mundipagg/offlineretry');
	}

	public static function offlineRetryIsEnabled() {
		return Mage::getStoreConfig('payment/mundipagg_standard/offline_retry_enabled');
	}

	public function loadByIncrementId($incrementId) {
		return $this->load($incrementId, 'order_increment_id');
	}
}