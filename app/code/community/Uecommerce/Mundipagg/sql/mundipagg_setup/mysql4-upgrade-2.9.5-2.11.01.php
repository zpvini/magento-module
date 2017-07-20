<?php
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



$result = $reader->query('select attribute_code from '.$prefix.'eav_attribute WHERE attribute_code = "mundipagg_recurrence_mix"')->fetchAll();
if (empty($result)) {
    $recurrenceMix = array(
      'attribute_set'           => 'Default',
      'group'                   => 'Recorrência',
      'label'                   => 'Permitir venda avulsa?',
      'note'                    => 'O cliente escolhe se vai comprar com recorrência ou não.',
      'visible'                 => true,
      'type'                    => 'int',
      'input'                   => 'select',
      'system'                  => false,
      'required'                => true,
      'used_in_product_listing' => true,
      'is_visible_on_front'     => true,
      'visible_on_front'        => true,
      'frontend_input'          => 'select',
      'source'                  => 'eav/entity_attribute_source_boolean'
    );

    $installer->addAttribute('catalog_product', 'mundipagg_recurrence_mix', $recurrenceMix);
}

//
$result = $reader->query('select attribute_code from '.$prefix.'eav_attribute WHERE attribute_code = "mundipagg_recurrence_discount"')->fetchAll();
if (empty($result)) {
    $recurrenceDiscount = array(
      'attribute_set'           => 'Default',
      'group'                   => 'Recorrência',
      'label'                   => 'Desconto no valor à vista(%)',
      'note'                    => 'Porcentagem de desconto para compra à vista.',
      'visible'                 => true,
      'type'                    => 'int',
      'input'                   => 'text',
      'system'                  => false,
      'required'                => false,
      'used_in_product_listing' => true,
      'is_visible_on_front'     => true,
      'visible_on_front'        => true,
      'frontend_input'          => 'text',
      'default'                 => ''
    );

    $installer->addAttribute('catalog_product', 'mundipagg_recurrence_discount', $recurrenceDiscount);
}

$installer->cleanCache();
$installer->endSetup();
