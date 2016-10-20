<?php
/**
 * Uecommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Uecommerce EULA.
 * It is also available through the world-wide-web at this URL:
 * http://www.uecommerce.com.br/
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the extension
 * to newer versions in the future. If you wish to customize the extension
 * for your needs please refer to http://www.uecommerce.com.br/ for more information
 *
 * @category   Uecommerce
 * @package    Uecommerce_Mundipagg
 * @copyright  Copyright (c) 2012 Uecommerce (http://www.uecommerce.com.br/)
 * @license    http://www.uecommerce.com.br/
 */

/**
 * Mundipagg Payment module
 *
 * @category   Uecommerce
 * @package    Uecommerce_Mundipagg
 * @author     Uecommerce Dev Team
 */
class Uecommerce_Mundipagg_Helper_Data extends Mage_Core_Helper_Abstract {
	protected $config_juros;

	/**
	 * Get extension version
	 */
	public function getExtensionVersion() {
		return (string)Mage::getConfig()->getNode()->modules->Uecommerce_Mundipagg->version;
	}

	/**
	 * Return issuer
	 * @param varchar $cardType
	 */
	public function issuer($cardType) {
		if ($cardType == '') {
			return '';
		} else {
			$issuers = array(
				'VI' => 'Visa',
				'MC' => 'Mastercard',
				'AE' => 'Amex',
				'DI' => 'Diners',
				'HI' => 'Hipercard',
				'EL' => 'Elo',
			);

			foreach ($issuers as $key => $issuer) {
				if ($key == $cardType) {
					return $issuer;
				}
			}
		}
	}

	/**
	 * Return cardType
	 * @param string $issuer
	 */
	public function getCardTypeByIssuer($issuer) {
		if ($issuer == '') {
			return '';
		} else {
			$issuers = array(
				'VI' => 'Visa',
				'MC' => 'Mastercard',
				'AE' => 'Amex',
				'DI' => 'Diners',
				'HI' => 'Hipercard',
				'EL' => 'Elo',
			);

			foreach ($issuers as $key => $cardType) {
				if ($cardType == $issuer) {
					return $key;
				}
			}
		}
	}

	/**
	 * Get credit cards number
	 */
	public function getCreditCardsNumber($payment_method) {
		$num = 1;

		switch ($payment_method) {
			case 'mundipagg_creditcardoneinstallment':
				$num = 0;
				break;

			case 'mundipagg_creditcard':
				$num = 1;
				break;

			case 'mundipagg_twocreditcards':
				$num = 2;
				break;

			case 'mundipagg_threecreditcards':
				$num = 3;
				break;

			case 'mundipagg_fourcreditcards':
				$num = 4;
				break;

			case 'mundipagg_fivecreditcards':
				$num = 5;
				break;
		}

		return $num;
	}

	/**
	 * Return payment method
	 */
	public function getPaymentMethod($num) {
		$method = '';

		switch ($num) {
			case '0':
				$method = 'mundipagg_creditcardoneinstallment';
				break;
			case '1':
				$method = 'mundipagg_creditcard';
				break;
			case '2':
				$method = 'mundipagg_twocreditcards';
				break;
			case '3':
				$method = 'mundipagg_threecreditcards';
				break;
			case '4':
				$method = 'mundipagg_fourcreditcards';
				break;
			case '5':
				$method = 'mundipagg_fivecreditcards';
				break;
		}

		return $method;
	}

	public function validateExpDate($expYear, $expMonth) {
		$date = Mage::app()->getLocale()->date();
		if (!$expYear || !$expMonth || ($date->compareYear($expYear) == 1)
			|| ($date->compareYear($expYear) == 0 && ($date->compareMonth($expMonth) == 1))
		) {
			return false;
		}

		return true;
	}

	/**
	 * Validate credit card number
	 *
	 * @param   string $cc_number
	 * @return  bool
	 */
	public function validateCcNum($ccNumber) {
		$cardNumber = strrev($ccNumber);
		$numSum = 0;

		for ($i = 0; $i < strlen($cardNumber); $i++) {
			$currentNum = substr($cardNumber, $i, 1);

			/**
			 * Double every second digit
			 */
			if ($i % 2 == 1) {
				$currentNum *= 2;
			}

			/**
			 * Add digits of 2-digit numbers together
			 */
			if ($currentNum > 9) {
				$firstNum = $currentNum % 10;
				$secondNum = ($currentNum - $firstNum) / 10;
				$currentNum = $firstNum + $secondNum;
			}

			$numSum += $currentNum;
		}

		/**
		 * If the total has no remainder it's OK
		 */
		return ($numSum % 10 == 0);
	}

	/**
	 * Validate CPF
	 */
	public function validateCPF($cpf) {
		// Verifiva se o número digitado contém todos os digitos
		$cpf = preg_replace('[\D]', '', $cpf);

		// Verifica se nenhuma das sequências abaixo foi digitada, caso seja, retorna falso
		if (strlen($cpf) != 11 ||
			$cpf == '00000000000' ||
			$cpf == '11111111111' ||
			$cpf == '22222222222' ||
			$cpf == '33333333333' ||
			$cpf == '44444444444' ||
			$cpf == '55555555555' ||
			$cpf == '66666666666' ||
			$cpf == '77777777777' ||
			$cpf == '88888888888' ||
			$cpf == '99999999999'
		) {
			return false;
		} else {   // Calcula os números para verificar se o CPF é verdadeiro
			for ($t = 9; $t < 11; $t++) {
				for ($d = 0, $c = 0; $c < $t; $c++) {
					$d += $cpf{$c} * (($t + 1) - $c);
				}

				$d = ((10 * $d) % 11) % 10;

				if ($cpf{$c} != $d) {
					return false;
				}
			}

			return true;
		}
	}

	/**
	 * Validate CNPJ
	 */
	public function validateCNPJ($value) {
		$cnpj = str_replace(array("-", " ", "/", "."), "", $value);
		$digitosIguais = 1;

		if (strlen($cnpj) < 14 && strlen($cnpj) < 15) {
			return false;
		}
		for ($i = 0; $i < strlen($cnpj) - 1; $i++) {

			if ($cnpj{$i} != $cnpj{$i + 1}) {
				$digitosIguais = 0;
				break;
			}
		}

		if (!$digitosIguais) {
			$tamanho = strlen($cnpj) - 2;
			$numeros = substr($cnpj, 0, $tamanho);
			$digitos = substr($cnpj, $tamanho);
			$soma = 0;
			$pos = $tamanho - 7;
			for ($i = $tamanho; $i >= 1; $i--) {
				$soma += $numeros{$tamanho - $i} * $pos--;
				if ($pos < 2) {
					$pos = 9;
				}
			}
			$resultado = ($soma % 11 < 2 ? 0 : 11 - $soma % 11);
			if ($resultado != $digitos{0}) {
				return false;
			}
			$tamanho = $tamanho + 1;
			$numeros = substr($cnpj, 0, $tamanho);
			$soma = 0;
			$pos = $tamanho - 7;
			for ($i = $tamanho; $i >= 1; $i--) {
				$soma += $numeros{$tamanho - $i} * $pos--;
				if ($pos < 2) {
					$pos = 9;
				}
			}
			$resultado = ($soma % 11 < 2 ? 0 : 11 - $soma % 11);
			if ($resultado != $digitos{1}) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	/**
	 * Apply telephone mask
	 */
	public function applyTelephoneMask($string) {
		$string = preg_replace('[\D]', '', $string);

		$length = strlen($string);

		switch ($length) {
			case 10:
				$mask = '(##)########';
				break;

			case 11:
				$mask = '(##)#########';
				break;

			default:
				return '';
		}

		for ($i = 0; $i < strlen($string); $i++) {
			$mask[strpos($mask, "#")] = $string[$i];
		}

		return '55' . $mask;
	}

	/**
	 * PhoneRequestCollection
	 *
	 * @param $order Mage_Sales_Model_Order
	 * @return array
	 */
	public function getPhoneRequestCollection(Mage_Sales_Model_Order $order) {
		$billingAddress = $order->getBillingAddress();
		$telephone = $billingAddress->getTelephone();

		$telephone = $this->applyTelephoneMask($telephone);

		if (!$telephone) {
			$telephone = '55(21)88888888';
		}

		$phoneTypeEnum = 'Residential';
		if ($this->validateCNPJ($order->getCustomerTaxvat())) {
			$phoneTypeEnum = 'Comercial';
		}

		$dataReturn = array(
			array(
				'AreaCode'      => substr($telephone, 3, 2),
				'CountryCode'   => substr($telephone, 0, 2),
				'Extension'     => '',
				'PhoneNumber'   => substr($telephone, 6, strlen($telephone)),
				'PhoneTypeEnum' => $phoneTypeEnum
			)
		);

		return $dataReturn;
	}

	/**
	 * Retorna o valor de uma parcela
	 * - valor total a ser parcelado
	 * - taxa de juros
	 * - numero de prestacoes
	 *
	 * Thanks to Fillipe Almeida Dutra
	 */
	public function calcInstallmentValue($total, $interest, $periods) {
		/*
		 * Formula do coeficiente:
		 *
		 * juros / ( 1 - 1 / (1 + i)^n )
		 *
		 */

		// calcula o coeficiente, seguindo a formula acima
		$coefficient = pow((1 + $interest), $periods);
		$coefficient = 1 / $coefficient;
		$coefficient = 1 - $coefficient;
		$coefficient = $interest / $coefficient;

		// retorna o valor da parcela
		return ($total * $coefficient);
	}

	public function getJurosParcela($total, $parcela) {
		$juros = $this->getJurosParcelaEscolhida($parcela);

		if ($juros) {
			return $total * $juros / $parcela;
		} else {
			return $total / $parcela;
		}
	}

	public function getTotalJuros($total, $parcela) {
		return $this->getJurosParcela($total, $parcela) * $parcela;
	}

	public function getConfigJuros($position) {
		$configJuros = $this->config_juros;

		if (empty($configJuros)) {
			$storeId = Mage::app()->getStore()->getStoreId();

			$value2 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_2', $storeId);
			$value3 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_3', $storeId);
			$value4 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_4', $storeId);
			$value5 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_5', $storeId);
			$value6 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_6', $storeId);
			$value7 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_7', $storeId);
			$value8 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_8', $storeId);
			$value9 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_9', $storeId);
			$value10 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_10', $storeId);
			$value11 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_11', $storeId);
			$value12 = Mage::getStoreConfig('payment/mundipagg_standard/installment_interest_value_12', $storeId);

			$this->config_juros = array(
				$this->prepareCalc($value2 ? $value2 : 0),
				$this->prepareCalc($value3 ? $value3 : 0),
				$this->prepareCalc($value4 ? $value4 : 0),
				$this->prepareCalc($value5 ? $value5 : 0),
				$this->prepareCalc($value6 ? $value6 : 0),
				$this->prepareCalc($value7 ? $value7 : 0),
				$this->prepareCalc($value8 ? $value8 : 0),
				$this->prepareCalc($value9 ? $value9 : 0),
				$this->prepareCalc($value10 ? $value10 : 0),
				$this->prepareCalc($value11 ? $value11 : 0),
				$this->prepareCalc($value12 ? $value12 : 0)
			);
		}

		return $this->config_juros[$position];
	}

	public function prepareCalc($value) {
		return (float)$value;
	}

	public function getJurosParcelaEscolhida($parcela) {
		$juros = 0;

		if ($parcela == 2) {
			$juros = $this->getConfigJuros(0);
		}

		if ($parcela > 2 && $parcela <= 3) {
			$juros = $this->getConfigJuros(1);
		}

		if ($parcela > 3 && $parcela <= 4) {
			$juros = $this->getConfigJuros(2);
		}

		if ($parcela > 4 && $parcela <= 5) {
			$juros = $this->getConfigJuros(3);
		}

		if ($parcela > 5 && $parcela <= 6) {
			$juros = $this->getConfigJuros(4);
		}

		if ($parcela > 6 && $parcela <= 7) {
			$juros = $this->getConfigJuros(5);
		}

		if ($parcela > 7 && $parcela <= 8) {
			$juros = $this->getConfigJuros(6);
		}

		if ($parcela > 8 && $parcela <= 9) {
			$juros = $this->getConfigJuros(7);
		}

		if ($parcela > 9 && $parcela <= 10) {
			$juros = $this->getConfigJuros(8);
		}

		if ($parcela > 10 && $parcela <= 11) {
			$juros = $this->getConfigJuros(9);
		}

		if ($parcela > 11) {
			$juros = $this->getConfigJuros(10);
		}

		return $juros;
	}

	public function isAntiFraudEnabled() {
		$antifraud = Mage::getStoreConfig('payment/mundipagg_standard/antifraud');

		return boolval($antifraud);
	}

	public function priceFormatter($amountInCents) {
		$number = round($amountInCents, 2, PHP_ROUND_HALF_DOWN);
		$number = number_format($number, 2, ',', '');
		return $number;
	}

	public function formatPriceToCents($oldPrice) {
		$newPrice = str_replace(",", ".", $oldPrice);
		$newPrice *= 100;

		return $newPrice;
	}

	public function priceInCentsToFloat($priceInCents) {
		$priceFormatted = $this->priceFormatter($priceInCents / 100);
		$newPrice = str_replace(",", ".", $priceFormatted);

		return $newPrice;
	}

	/**
	 * Check if input value is a valid number
	 * @param $value
	 * @return bool
	 */
	public function isValidNumber($value) {
		$value = str_replace(',', '.', $value);
		$arrVal = explode('.', $value);
		$cents = $arrVal[1];

		if (strlen($cents) > 2 || !is_numeric($value)){
			return false;
		}

		return true;
	}

	/**
	 * Check if array index "isset", if true, return the array index data, else, return $defaultValue
	 *
	 * @param      $index
	 * @param null $defaultValue
	 * @return mixed $index || $defaultValue
	 * @author Ruan Azevedo <razevedo@mundipagg.com>
	 */
	public function issetOr(&$index, $defaultValue = null) {
		if (isset($index)) {
			return $index;
		} else {
			return $defaultValue;
		}
	}

	public function getAntifraudName($antifraudNumber = null) {

		if (is_null($antifraudNumber)) {
			$antifraudNumber = intval(Mage::getStoreConfig('payment/mundipagg_standard/antifraud_provider'));
		} else {
			$antifraudNumber = intval($antifraudNumber);
		}

		$antifraudProvider = null;

		switch ($antifraudNumber) {
			case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_CLEARSALE:
				$antifraudProvider = 'clearsale';
				break;

			case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_FCONTROL:
				$antifraudProvider = 'fcontrol';
				break;

			case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_STONE:
				$antifraudProvider = 'stone';
				break;
		}

		return $antifraudProvider;
	}

}
