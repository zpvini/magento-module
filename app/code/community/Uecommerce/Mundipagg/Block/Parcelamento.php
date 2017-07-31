<?php

class Uecommerce_Mundipagg_Block_Parcelamento extends Mage_Core_Block_Template
{
	protected $_price = null;
	protected $_mundipagg_recurrence = null;
	protected $_mundipagg_recurrences = null;
	protected $_mundipagg_frequency = null;
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
		$this->setMundipaggRecurrence($this->getData('mundipagg_recurrence'));
		$this->setMundipaggRecurrences($this->getData('mundipagg_recurrences'));
		$this->setMundipaggFrequency($this->getData('mundipagg_frequency_enum'));
		$this->setMundipaggRecurrenceMix($this->getData('mundipagg_recurrence_mix'));
		$this->setMundipaggRecurrenceDiscount($this->getData('mundipagg_recurrence_discount'));
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
    
    function getMundipaggFrequency()
    {
        return $this->_mundipagg_frequency;
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
    
    function setMundipaggFrequency($_mundipagg_frequency)
    {
        $this->_mundipagg_frequency = $_mundipagg_frequency;
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