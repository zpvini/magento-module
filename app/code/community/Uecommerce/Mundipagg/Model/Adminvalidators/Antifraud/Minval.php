<?php

class Uecommerce_Mundipagg_Model_Adminvalidators_Antifraud_Minval extends Mage_Core_Model_Config_Data {

	public function save() {
		$helper = Mage::helper('mundipagg');
		$value = $this->getValue();

		if (empty($value)) {
			$this->setValue('0.00');

			return parent::save();
		}

		$valueInCents = $helper->formatPriceToCents($value);
		$groups = $this->getGroups();
		$antifraudProvider = $helper->issetOr($groups['mundipagg_standard']['fields']['antifraud_provider']['value']);
		$afProviderName = $helper->getAntifraudName($antifraudProvider);
		$afProviderNameCaptilized = strtoupper($afProviderName);

		if ($helper->isValidNumber($value) === false) {
			$errMsg = $helper->__("Order minimum value '%s' for antifraud %s isn't in the valid format", $value, $afProviderNameCaptilized);
			Mage::throwException($errMsg);
		}

		if ($valueInCents < 0) {
			$errMsg = $helper->__("Order minimum value for antifraud %s can't be negative", $afProviderNameCaptilized);
			Mage::throwException($errMsg);
		}

		$floatValue = $helper->priceInCentsToFloat($valueInCents);
		$this->setValue($floatValue);

		return parent::save();
	}

}