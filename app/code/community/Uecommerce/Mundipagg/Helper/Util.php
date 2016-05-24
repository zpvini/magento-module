<?php

class Uecommerce_Mundipagg_Helper_Util extends Mage_Core_Helper_Abstract {

	public function jsonEncodePretty($input) {
		return json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}

} 