<?php
$installer = $this;
$prefix = Mage::getConfig()->getTablePrefix();

$installer->startSetup();
$installer->run("

CREATE TABLE IF NOT EXISTS {$prefix}mundipagg_offline_retry (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_increment_id VARCHAR(50) NOT NULL,
  create_date DATETIME NOT NULL COMMENT 'MundiPagg returned create date',
  deadline DATETIME NOT NULL COMMENT 'MundiPagg time to retry the authorization'
)  ENGINE=InnoDB  DEFAULT CHARSET=utf8;
");

$installer->endSetup();
