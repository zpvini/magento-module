<?php

class Uecommerce_Mundipagg_Helper_Log extends Mage_Core_Helper_Abstract {

	private $level;

	public function info($msg) {
		$this->level = Zend_Log::INFO;
		$this->write($msg);
	}

	public function debug($msg) {
		$this->level = Zend_Log::DEBUG;
		$this->write($msg);
	}

	public function error($msg) {
		$this->level = Zend_Log::ERR;
		$this->write($msg);
	}

	private function write($msg) {
		$file = "Uecommerce_Mundipagg_" . date('Y-m-d') . ".log";
		Mage::log($msg, $this->level, $file);
	}

} 