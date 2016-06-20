<?php

/**
 * @author Ruan Azevedo <razevedo@mundipagg.com>
 * @since 2016-06-20
 */
class Uecommerce_Mundipagg_Model_OfflineRetry extends Mage_Core_Model_Abstract {

	protected function _construct() {
		$this->_init('mundipagg/offline_retry');
	}

	public function teste() {
		return 'teste ok';
	}

}