<?php

class Uecommerce_Mundipagg_Model_Urlvalidation extends Mage_Core_Model_Config_Data {

	public function save() {
		$url = $this->getValue();
		$parsedUrl = parse_url($url);
		$path = null;

		if (isset($parsedUrl['path'])) {
			$path = substr_replace('/', $parsedUrl['path']);
		}

		if (is_null($path) || strtolower($path) != 'sale') {
			$parsedUrl['path'] = 'Sale/';
		}

		$newUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}/{$parsedUrl['path']}";

		$this->setValue($newUrl);

		return parent::save();
	}

}