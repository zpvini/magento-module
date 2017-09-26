<?php

class Uecommerce_Mundipagg_Model_Overwriteflags extends Mage_Core_Model_Config_Data
{
    public function save()
    {
        $overwrite = $this->getValue();

        try {
            Mage::getConfig()->saveConfig('payment/mundipagg_standard/overwrite_magento_flags', $overwrite);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return parent::save();
    }
}
