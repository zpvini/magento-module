<?php

$installer = $this;
$prefix = Mage::getConfig()->getTablePrefix();

$installer->startSetup();

$sql = "
UPDATE {$prefix}core_config_data
SET VALUE = 0
WHERE path IN (
  'payment/mundipagg_threecreditcards/active',
  'payment/mundipagg_fourcreditcards/active',
  'payment/mundipagg_fivecreditcards/active'
);";

$installer->run($sql);
$installer->endSetup();
