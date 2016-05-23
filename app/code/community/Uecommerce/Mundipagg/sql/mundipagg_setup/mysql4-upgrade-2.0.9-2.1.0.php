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
 * @copyright  Copyright (c) 2012 Uecommerce (http://www.uecommerce.com.br/)
 * @license    http://www.uecommerce.com.br/
 */

/**
 * Mundipagg Payment module
 *
 * @category   Uecommerce
 * @package    Uecommerce_Mundipagg
 * @author     Uecommerce Dev Team
 */

$installer = new Mage_Catalog_Model_Resource_Eav_Mysql4_Setup('core_setup');

$installer->startSetup();

$reader = Mage::getSingleton('core/resource')->getConnection('core_read');

$result = $reader->query('select attribute_code from '.$prefix.'eav_attribute WHERE attribute_code = "mundipagg_recurrent"')->fetchAll();

if (empty($result)) {
    $recurrent = array(
      'attribute_set'           => 'Default',
      'group'                   => 'Recorrência',
      'label'                   => 'Produto de assinatura?',
      'visible'                 => true,
      'type'                    => 'int',
      'input'                   => 'select',
      'source'                  => 'eav/entity_attribute_source_boolean',
      'system'                  => false,
      'required'                => true,
      'used_in_product_listing' => true,
      'is_visible_on_front'     => true,
      'visible_on_front'        => true
    );

    $installer->addAttribute('catalog_product', 'mundipagg_recurrent', $recurrent);
}

$result = $reader->query('select attribute_code from '.$prefix.'eav_attribute WHERE attribute_code = "mundipagg_frequency_enum"')->fetchAll();

if (empty($result)) {
    $frequency_enum = array(
      'attribute_set'           => 'Default',
      'group'                   => 'Recorrência',
      'label'                   => 'Frequência',
      'note'                    => 'O valor colocado na aba "Prices / Preços" sera o valor pago por: semana / mensal / trimestral / semestral / anual',
      'visible'                 => true,
      'type'                    => 'varchar',
      'input'                   => 'select',
      'system'                  => false,
      'required'                => true,
      'used_in_product_listing' => true,
      'is_visible_on_front'     => true,
      'visible_on_front'        => true,
      'frontend_input'          => 'select',
      'source'                  => 'mundipagg/source_frequency'
    );

    $installer->addAttribute('catalog_product', 'mundipagg_frequency_enum', $frequency_enum);
}

$result = $reader->query('select attribute_code from '.$prefix.'eav_attribute WHERE attribute_code = "mundipagg_recurrences"')->fetchAll();

if (empty($result)) {
    $recurrences = array(
      'attribute_set'           => 'Default',
      'group'                   => 'Recorrência',
      'label'                   => 'Número de Ciclos',
      'note'                    => 'Número de ciclos que serão cobrados antes de uma renovação da parte do cliente.',
      'visible'                 => true,
      'type'                    => 'int',
      'input'                   => 'text',
      'system'                  => false,
      'required'                => true,
      'used_in_product_listing' => true,
      'is_visible_on_front'     => true,
      'visible_on_front'        => true,
      'frontend_input'          => 'text',
      'default'                 => '1'
    );

    $installer->addAttribute('catalog_product', 'mundipagg_recurrences', $recurrences);
}

$installer->endSetup();
