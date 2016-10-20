<?php

class Uecommerce_Mundipagg_Model_Adminvalidators_Antifraud_Minval extends Mage_Core_Model_Config_Data {

	const DISABLED = 0;
	const ENABLED  = 1;

	public function save() {
		$helper = Mage::helper('mundipagg');
		$value = $this->getValue();

		if (empty($value)) {
			$this->setValue(0);

			return parent::save();
		}

		$valueInCents = $helper->formatPriceToCents($value);
		$groups = $this->getGroups();
		$antifraudProvider = $helper->issetOr($groups['mundipagg_standard']['fields']['antifraud_provider']['value']);
		$afProviderName = $helper->getAntifraudName($antifraudProvider);
		$afProviderNameCaptilized = strtoupper($afProviderName);

		if ($valueInCents < 0) {
			$errMsg = $helper->__("Cart minimum value for antifraud %s can't be negative", $afProviderNameCaptilized);
			Mage::throwException($errMsg);
		}

//		$value =

		return parent::save();
	}

}