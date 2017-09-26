<?php

class Uecommerce_Mundipagg_Model_Adminvalidators_Timeout extends Mage_Core_Model_Config_Data
{

    const DISABLED = 0;
    const ENABLED  = 1;

    public function save()
    {
        $limit = $this->getValue();
        $helper = Mage::helper('mundipagg');

        if (is_numeric($limit) === false) {
            $errMsg = $helper->__("Integration timeout limit must be an numeric value");
            Mage::throwException($errMsg);
        }

        return parent::save();
    }
}
