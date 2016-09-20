<?php

class Uecommerce_Mundipagg_Helper_Util extends Mage_Core_Helper_Abstract {

	public function jsonEncodePretty($input) {
		$version = phpversion();
		$version = explode('.', $version);
		$version = $version[0] . $version[1];
		$version = intval($version);

		// JSON Variables available only in PHP 5.4
		if ($version <= 53) {
			$result = json_encode($input);

		} else {
			$result = json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		}

		return $result;
	}

} 