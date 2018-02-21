<?php

class Uecommerce_Mundipagg_Helper_Antifraud extends Mage_Core_Helper_Abstract
{
    public function getMinimumValue()
    {
        $standard = Mage::getModel('mundipagg/standard');
        if ($standard->getAntiFraud()) {

            $configString = 'payment/mundipagg_standard/antifraud_provider';

            $antifraudProviderConfig = intval(Mage::getStoreConfig($configString));
            $antifraudProvider = null;

            switch ($antifraudProviderConfig) {
                case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_CLEARSALE:
                    $antifraudProvider = 'clearsale';
                    break;
                case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_FCONTROL:
                    $antifraudProvider = 'fcontrol';
                    break;
                case Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_STONE:
                    $antifraudProvider = 'stone';
                    break;
            }

            $configString = "payment/mundipagg_standard/antifraud_minimum_{$antifraudProvider}";
            return Mage::getStoreConfig($configString);
        }
    }
}