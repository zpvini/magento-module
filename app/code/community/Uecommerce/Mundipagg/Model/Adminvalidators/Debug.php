<?php

class Uecommerce_Mundipagg_Model_Adminvalidators_Debug extends Mage_Core_Model_Config_Data {

	const DISABLED = 0;
	const ENABLED  = 1;

	public function save() {
		$debug = $this->getValue();
		$pathLogConfig = 'dev/log/active';
		$logIsActive = Mage::getStoreConfig($pathLogConfig);

		if ($debug == self::ENABLED && $logIsActive == self::DISABLED) {
			try {
				Mage::getConfig()->saveConfig($pathLogConfig, self::ENABLED);
			} catch (Exception $e) {
				throw new Exception($e->getMessage());
			}
		}

		return parent::save();
	}

}