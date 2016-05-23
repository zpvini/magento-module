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

class Uecommerce_Mundipagg_Block_Parcelamento extends Mage_Core_Block_Template
{
	protected $_price = null;

	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('mundipagg/parcelamento.phtml');


	}

	protected function _beforeToHtml()
	{
		$this->setPrice($this->getData('price'));
		$this->setParcelamentoProduto($this->getData('parcelamento_produto'));
	}

	public function setPrice($price)
	{
		$this->_price = $price;
	}

	public function getPrice()
	{
		return $this->_price;
	}

	public function setParcelamentoProduto($parcelamento)
	{
		$this->_parcelamento = $parcelamento;
	}

	public function getParcelamentoProduto()
	{
		return $this->_parcelamento;
	}

	/**
	* Call it on category or product page
	* echo $this->getLayout()->createBlock("mundipagg/parcelamento")->setData('price', $_product->getPrice())->toHtml();
	*/
	public function getParcelamento()
	{
		$active = Mage::getStoreConfig('payment/mundipagg_creditcard/active');

		if ($active) {
            $parcelamento = Mage::getStoreConfig('payment/mundipagg_standard/product_pages_installment_default');

            $installmentsHelper = Mage::helper('mundipagg/installments');
            $installmentsHelper->displayTotal = false;
            $parcelamentoMax = $installmentsHelper->getInstallmentForCreditCardType($parcelamento, $this->getPrice());

            return end($parcelamentoMax);
        }
	}

	/**
	* Call it on category or product page
	* echo $this->getLayout()->createBlock("mundipagg/parcelamento")->setData('price', $_product->getPrice())->setData('parcelamento_produto', $_product->getParcelamento())->toHtml();
	*/
	public function getParcelamentoCustom()
	{
		if ($this->getParcelamentoProduto() == '') {
			return 3;
		} else {
			return $this->getParcelamentoProduto();			
		}
	}
}