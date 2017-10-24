<?php

class Uecommerce_Mundipagg_Model_Source_Debit
{
    public function toOptionArray()
    {
        return array(
            array('value' => '001',                'label' => 'Banco Do Brasil'),
            array('value' => '237',                'label' => 'Bradesco'),
            array('value' => '341',                'label' => 'Itaú'),
            array('value' => 'VBV',                'label' => 'VBV'),
            array('value' => 'cielo_mastercard',   'label' => 'Mastercard'),
            array('value' => 'cielo_visa',         'label' => 'Visa'),
        );
    }

    public function getDebitServiceNames()
    {
        return array(
          '001' => 'BancoDoBrasil',
          '237' => 'Bradesco',
          '341' => 'Itau',
          'VBV' => 'VBV',
          'cielo_mastercard' => 'Mastercard',
          'cielo_visa' => 'Visa',
        );
    }
}
