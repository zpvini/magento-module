<?php

class Uecommerce_Mundipagg_Model_Resource_Offlineretry extends Mage_Core_Model_Mysql4_Abstract {

	public function _construct() {
		$this->_init('mundipagg/mundipagg_offline_retry', 'id');
	}
}