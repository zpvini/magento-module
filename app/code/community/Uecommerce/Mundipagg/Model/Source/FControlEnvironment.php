<?php

/**
 * @author Ruan Azevedo <razevedo@mundipagg.com>
 * @since 2016-06-14
 * Class Uecommerce_Mundipagg_Model_Source_Environment
 */
class Uecommerce_Mundipagg_Model_Source_FControlEnvironment
{

    const SANDBOX    = 1;
    const PRODUCTION = 2;
    
    const CONFIG_STRING = 'payment/mundipagg_standard/environment_fcontrol';

    public function toOptionArray()
    {
        return
            array(
                array('value' => self::SANDBOX, 'label' => Mage::helper('mundipagg')->__('Sandbox')),
                array('value' => self::PRODUCTION, 'label' => Mage::helper('mundipagg')->__('Production')),
            );
    }
    
    public static function getEnvironment()
    {
        return Mage::getStoreConfig(self::CONFIG_STRING);
    }
}
