<?php

$installer = $this;
$prefix = Mage::getConfig()->getTablePrefix();

$installer->startSetup();

$sql = "
ALTER TABLE {$prefix}mundipagg_transactions
ADD COLUMN is_reccurency BINARY(1) NULL DEFAULT '0' AFTER error";

$installer->run($sql);
$installer->endSetup();