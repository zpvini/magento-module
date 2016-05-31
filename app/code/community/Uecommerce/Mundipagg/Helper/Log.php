<?php

/**
 * Log helper
 * @author Ruan Azevedo <razvedo@mundipagg.com>
 */
class Uecommerce_Mundipagg_Helper_Log extends Mage_Core_Helper_Abstract {

	private $level;
	private $method;

	public function __construct($method = '') { $this->method = $method; }

	public function setMethod($method) {
		$this->method = $method;
	}

	public function info($msg) {
		$this->level = Zend_Log::INFO;
		$this->write($msg);
	}

	public function debug($msg) {
		$this->level = Zend_Log::DEBUG;
		$this->write($msg);
	}

	public function warning($msg) {
		$this->level = Zend_Log::WARN;
		$this->write($msg);
	}

	public function error($msg, $logExceptionFile = false) {
		$this->level = Zend_Log::ERR;
		$this->write($msg);

		if ($logExceptionFile) {
			Mage::logException($msg);
		}
	}

	private function write($msg) {
		$file = "Mundipagg_Integracao_" . date('Y-m-d') . ".log";

		if (!empty($this->method)) {
			$msg = "[{$this->method}] {$msg}";
		}

		Mage::log($msg, $this->level, $file);
	}

} 