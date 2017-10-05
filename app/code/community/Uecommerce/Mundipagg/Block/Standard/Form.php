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

class Uecommerce_Mundipagg_Block_Standard_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('mundipagg/form.phtml');

        // Get Customer Credit Cards Saved On File
        if ($this->helper('customer')->isLoggedIn()) {
            $entityId = Mage::getSingleton('customer/session')->getId();

            $ccsCollection = Mage::getResourceModel('mundipagg/cardonfile_collection')
                ->addEntityIdFilter($entityId)
                ->addExpiresAtFilter();

            $this->setCcs($ccsCollection);
        } elseif (Mage::getSingleton('adminhtml/session_quote')->getQuote()->getCustomerId()) {
            $entityId = Mage::getSingleton('adminhtml/session_quote')->getQuote()->getCustomerId();

            $ccsCollection = Mage::getResourceModel('mundipagg/cardonfile_collection')
                ->addEntityIdFilter($entityId)
                ->addExpiresAtFilter();

            $this->setCcs($ccsCollection);
        } else {
            $this->setCcs(array());
        }
    }
    
    /**
     * Return Standard model
     */
    public function getStandard()
    {
        return Mage::getModel('mundipagg/standard');
    }

    /**
    * Get installments
    */
    public function getInstallments($ccType = null)
    {
        $session = Mage::getSingleton('admin/session');

        if ($session->isLoggedIn()) {
            $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        } else {
            $quote =(Mage::getModel('checkout/type_onepage') !== false)? Mage::getModel('checkout/type_onepage')->getQuote(): Mage::getModel('checkout/session')->getQuote();
        }

        $quote->setMundipaggInterest(0.0);
        $quote->setMundipaggBaseInterest(0.0);
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $quote->save();

        return Mage::helper('mundipagg/installments')->getInstallmentForCreditCardType($ccType);
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    public function loadOrder()
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($quote->getReservedOrderId());

        return $order;
    }
}
