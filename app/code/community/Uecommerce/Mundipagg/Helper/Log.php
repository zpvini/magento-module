<?php

/**
 * Log helper
 * @author Ruan Azevedo <razvedo@mundipagg.com>
 */
class Uecommerce_Mundipagg_Helper_Log extends Mage_Core_Helper_Abstract {

	private $level;
	private $method;
	private $logLabel = '';

	public function __construct($method = '') { $this->method = $method; }

	public function setMethod($method) {
		$this->method = $method;
	}

	public function setLogLabel($logLabel) {
		$this->logLabel = $logLabel;
	}

	public function getLogLabel() {
		return $this->logLabel;
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
		$exception = new Exception($msg);
		$this->level = Zend_Log::ERR;
		$this->write($msg);

		if ($logExceptionFile) {
			Mage::logException($exception);
		}
	}

	private function write($msg) {
		$debugIsEnabled = intval(Mage::getStoreConfig('payment/mundipagg_standard/debug'));

		if ($debugIsEnabled === false)
			return;

		$version = Mage::helper('mundipagg')->getExtensionVersion();
		$file = "Mundipagg_Integracao_" . date('Y-m-d') . ".log";
		$method = $this->method;
		$newMsg = "v{$version} ";

		if (!empty($method)) {
			$logLabel = $this->logLabel;

			if (!empty($logLabel)) {
				$newMsg .= "[{$this->method}] {$this->logLabel} | {$msg}";

			} else {
				$newMsg .= "[{$this->method}] {$msg}";
			}
		} else {
			$newMsg .= $msg;
		}

		Mage::log($newMsg, $this->level, $file);
	}

} 