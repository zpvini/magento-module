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

class Uecommerce_Mundipagg_Model_Source_Frequency extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    public function getAllOptions()
    {
        return array(
            array('value' => '0', 'label' => 'Nenhuma'),
            array('value' => 'Daily', 'label' => Mage::helper('mundipagg')->__('Daily')),
            array('value' => 'Weekly', 'label' => Mage::helper('mundipagg')->__('Weekly')),
            array('value' => 'Monthly', 'label' => Mage::helper('mundipagg')->__('Monthly')),
            array('value' => 'Quarterly', 'label' => Mage::helper('mundipagg')->__('Quarterly')),
            array('value' => 'Biannual', 'label' => Mage::helper('mundipagg')->__('Biannual')),
            array('value' => 'Yearly', 'label' => Mage::helper('mundipagg')->__('Yearly'))
        );
    }

    public function toOptionArray()
    {
        return $this->getAllOptions();
    }
}
