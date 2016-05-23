<?php
    /**
     * Uecommerce
     *
     * NOTICE OF LICENSE
     *
     * This source file is subject to the Uecommerce EULA.
     * It is also available through the world-wide-web at this URL:
     * http://www.uecommerce.com.br/
     *
     * DISCLAIMER
     *
     * Do not edit or add to this file if you wish to upgrade the extension
     * to newer versions in the future. If you wish to customize the extension
     * for your needs please refer to http://www.uecommerce.com.br/ for more information
     *
     * @category   Uecommerce
     * @package    Uecommerce_Mundipagg
     * @copyright  Copyright (c) 2014 Uecommerce (http://www.uecommerce.com.br/)
     * @license    http://www.uecommerce.com.br/
     */

    /**
     * Mundipagg Payment module
     *
     * @category   Uecommerce
     * @package    Uecommerce_Mundipagg
     * @author     Thanks to Fillipe Almeida Dutra
     */

    $installer = Mage::getResourceModel('sales/setup', 'default_setup');

    $installer->startSetup();

// Interests
    $installer->addAttribute('quote', 'mundipagg_base_interest',
        array(
            'label' => 'Base Interest',
            'type'  => 'decimal',
        )
    );

    $installer->addAttribute('quote', 'mundipagg_interest',
        array(
            'label' => 'Interest',
            'type'  => 'decimal',
        )
    );

    $installer->addAttribute('order', 'mundipagg_base_interest',
        array(
            'label' => 'Base Interest',
            'type'  => 'decimal',
        )
    );

    $installer->addAttribute('order', 'mundipagg_interest',
        array(
            'label' => 'Interest',
            'type'  => 'decimal',
        )
    );

    $installer->addAttribute('invoice', 'mundipagg_base_interest',
        array(
            'label' => 'Base Interest',
            'type'  => 'decimal',
        )
    );

    $installer->addAttribute('invoice', 'mundipagg_interest',
        array(
            'label' => 'Interest',
            'type'  => 'decimal',
        )
    );

    $installer->addAttribute('creditmemo', 'mundipagg_base_interest',
        array(
            'label' => 'Base Interest',
            'type'  => 'decimal',
        )
    );

    $installer->addAttribute('creditmemo', 'mundipagg_interest',
        array(
            'label' => 'Interest',
            'type'  => 'decimal',
        )
    );

    $installer->endSetup();