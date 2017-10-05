<?php
$installer = $this;
$installer->startSetup();

Mage::getConfig()->saveConfig('payment/mundipagg_standard/environment_fcontrol', 1);
Mage::getConfig()->reinit();
Mage::getConfig()->cleanCache();
Mage::app()->reinitStores();

$installer->endSetup();
