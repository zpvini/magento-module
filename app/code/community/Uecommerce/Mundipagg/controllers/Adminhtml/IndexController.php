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

class Uecommerce_Mundipagg_Adminhtml_IndexController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Constructor
     */
    protected function _construct() 
    {        
        $this->setUsedModuleName('mundipagg');
    }

    public function installmentsandinterestAction()
    {
        $post = $this->getRequest()->getPost();
        $result = array();
        $installmentsHelper = Mage::helper('mundipagg/installments');
        
        if(isset($post['cctype'])){
            $total = $post['total'];
            $cctype = $post['cctype'];

            if(!$total || $total == 0) {
                $total = null;
            }

            $installments = $installmentsHelper->getInstallmentForCreditCardType($cctype,$total);

            $result['installments'] = $installments;
            $result['brand'] = $cctype;
        } else {
            $installments = $installmentsHelper->getInstallmentForCreditCardType();
            $result['installments'] = $installments;
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
    
    public function setoldsettingsAction()
    {
        $path = 'payment/mundipagg_standard/';
        
        if(!Mage::getStoreConfig($path.'parcelamento_de2')) {
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array('error' => $this->__('Probably you had not installed the old version.'))));
            return;
        }      
        
        $settings = array();
        
        $settings[0] = array(Mage::getStoreConfig($path.'parcelamento_de2'),'1','');

        for($i=2;$i<=12;$i++) {
            $settings[] = array(Mage::getStoreConfig($path.'parcelamento_ate'.$i),$i, '');
        }
        
        Mage::getConfig()->saveConfig($path.'installments', serialize($settings));
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
        Mage::getConfig()->cleanCache();
        
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
            'success' => $this->__('The old settings have been successfully restored! Please check and make tests in your store.'))));
    }
}