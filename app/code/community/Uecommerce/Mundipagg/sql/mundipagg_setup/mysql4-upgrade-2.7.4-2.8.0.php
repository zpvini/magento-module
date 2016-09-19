<?php
$installer = $this;
$prefix = Mage::getConfig()->getTablePrefix();

$statusTable = $installer->getTable('sales/order_status');
$statusStateTable = $installer->getTable('sales/order_status_state');

$installer->getConnection()->insertArray(
	$statusTable,
	array(
		'status',
		'label'
	),
	array(
		array('status' => 'mundipagg_with_error', 'label' => 'With Error')
	)
);

// Insert states and mapping of statuses to states
$installer->getConnection()->insertArray(
	$statusStateTable,
	array(
		'status',
		'state',
		'is_default'
	),
	array(
		array(
			'status'     => 'mundipagg_with_error',
			'state'      => 'pending',
			'is_default' => 0
		)
	)
);
