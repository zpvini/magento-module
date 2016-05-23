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
class Uecommerce_Mundipagg_Block_Adminhtml_Form_Field_Installments extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    protected function _prepareToRender()
    {
        $this->addColumn('installment_boundary', array(
            'label' => Mage::helper('mundipagg')->__('Amount (incl.)'),
            'style' => 'width:100px',
        ));
        $this->addColumn('installment_frequency', array(
            'label' => Mage::helper('mundipagg')->__('Maximum Number of Installments'),
            'style' => 'width:100px',
        ));
        $this->addColumn('installment_interest', array(
            'label' => Mage::helper('mundipagg')->__('Interest Rate (%)'),
            'style' => 'width:100px',
        ));
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('mundipagg')->__('Add Installment Boundary');
    }
}
