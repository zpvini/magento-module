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

class Uecommerce_Mundipagg_Block_Adminhtml_System_Config_Form_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set template
     */
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('mundipagg/system/config/button.phtml');
    }
    
    /**
     * Return element html
     * 
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }
    
    /**
     * Return ajax url for button
     * 
     * @return string
     */
    public function getAjaxOldSettingsUrl(){
        return Mage::helper('adminhtml')->getUrl('mundipagg/adminhtml_index/setoldsettings');
    }
    
    /**
     * Generate button html
     * 
     * @return string
     */
    public function getButtonHtml(){
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
                    'id' => 'mundipagg_button',
                    'label' => $this->helper('adminhtml')->__('Set old settings'),
                    'onclick' => 'javascript:setOldSettings(); return false;'
                ));
        
        return $button->toHtml();
    }
}