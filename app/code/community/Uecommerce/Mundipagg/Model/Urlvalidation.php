<?php

class Uecommerce_Mundipagg_Model_Urlvalidation extends Mage_Core_Model_Config_Data {

	public function save() {
		$logHelper = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
		$url = $this->getValue();
		$parsedUrl = parse_url($url);
		$path = null;

		$logHelper->info("parsed: " . print_r($parsedUrl, 1));

		if (isset($parsedUrl['path'])) {
			$path = strtolower($parsedUrl['path']);
		}

		if (is_null($path) || $path != '/sale/') {
			$parsedUrl['path'] = 'Sale/';
		}

		$newUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}/{$parsedUrl['path']}";

		$logHelper->info("newUrl: {$newUrl}");

		$this->setValue($newUrl);

		return parent::save();
	}

}