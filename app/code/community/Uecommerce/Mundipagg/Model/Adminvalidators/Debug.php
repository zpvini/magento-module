<?php

class Uecommerce_Mundipagg_Model_Adminvalidators_Debug extends Mage_Core_Model_Config_Data {

	const INATIVE = 0;
	const ACTIVE  = 1;

	public function save() {
		$debug = $this->getValue();
		$log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);

		if ($debug == self::INATIVE)
			return;

		$pathLogConfig = 'dev/log/active';
		$logIsActive = Mage::getStoreConfig($pathLogConfig);

		if ($logIsActive == self::ACTIVE) //log already active
			return;

		try {
			Mage::getConfig()->saveConfig($pathLogConfig, self::ACTIVE);

		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}

		return parent::save();
	}

}