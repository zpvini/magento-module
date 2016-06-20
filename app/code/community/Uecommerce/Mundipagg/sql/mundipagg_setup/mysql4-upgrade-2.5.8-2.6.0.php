<?php
$installer = $this;
$prefix = Mage::getConfig()->getTablePrefix();

$installer->startSetup();
$installer->run("
CREATE TABLE IF NOT EXISTS `" . $prefix . "mundipagg_offline_retry` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `increment_id` varchar(50),
  `mundi_create_date` date,
  `deadline` date,
  PRIMARY KEY (`id`),
  KEY `increment_id` (`increment_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
");

$installer->endSetup();
