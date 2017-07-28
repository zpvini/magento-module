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
	protected $_mundipagg_recurrence = null;
	protected $_mundipagg_recurrences = null;
	protected $_mundipagg_recurrence_mix = null;
	protected $_mundipagg_recurrence_discount = null;

	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('mundipagg/parcelamento.phtml');


	}

	protected function _beforeToHtml()
	{
		$this->setPrice($this->getData('price'));
		$this->setParcelamentoProduto($this->getData('parcelamento_produto'));
		$this->setMundipaggRecurrent($this->getData('mundipagg_recurrence'));
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

    function getMundipaggRecurrence()
    {
        return $this->_mundipagg_recurrence;
    }

    function setMundipaggRecurrence($_mundipagg_recurrence)
    {
        $this->_mundipagg_recurrence = $_mundipagg_recurrence;
        return $this;
    }

    function getMundipaggRecurrences()
    {
        return $this->_mundipagg_recurrences;
    }

    function getMundipaggRecurrenceMix()
    {
        return $this->_mundipagg_recurrence_mix;
    }

    function getMundipaggRecurrenceDiscount()
    {
        return $this->_mundipagg_recurrence_discount;
    }

    function setMundipaggRecurrences($_mundipagg_recurrences)
    {
        $this->_mundipagg_recurrences = $_mundipagg_recurrences;
        return $this;
    }

    function setMundipaggRecurrenceMix($_mundipagg_recurrence_mix)
    {
        $this->_mundipagg_recurrence_mix = $_mundipagg_recurrence_mix;
        return $this;
    }

    function setMundipaggRecurrenceDiscount($_mundipagg_recurrence_discount)
    {
        $this->_mundipagg_recurrence_discount = $_mundipagg_recurrence_discount;
        return $this;
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
            $recurrence = $this->getMundipaggRecurrence();
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