<?php

class Uecommerce_Mundipagg_Helper_UnserializeArray extends Mage_Core_Helper_UnserializeArray {

	/**
	 * This module serialize the order interests information in an Varien_Object.
	 * Magento class Unserialize_Parser throws an Exception when an Varien_Object is unserialized.
	 * This rewrite catch this exception and get the serialized data with PHP native unserialize function
	 *
	 * @param string $str
	 * @return array|mixed
	 */
	public function unserialize($str) {
		try {
			$result = parent::unserialize($str);
		} catch (Exception $e) {
			$result = unserialize($str);
		}

		return $result;
	}

}