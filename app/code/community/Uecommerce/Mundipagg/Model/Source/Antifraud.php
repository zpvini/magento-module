<?php

class Uecommerce_Mundipagg_Model_Source_Antifraud
{

    const ANTIFRAUD_NONE = 0;
    const ANTIFRAUD_CLEARSALE = 1;
    const ANTIFRAUD_FCONTROL  = 2;
    const ANTIFRAUD_STONE  = 3;

    public function toOptionArray()
    {
        return array(
            array('value' => self::ANTIFRAUD_NONE, 'label' => 'Selecione...'),
            array('value' => self::ANTIFRAUD_STONE, 'label' => 'Stone'),
            array('value' => self::ANTIFRAUD_CLEARSALE, 'label' => 'Clearsale'),
            array('value' => self::ANTIFRAUD_FCONTROL, 'label' => 'FControl'),
        );
    }
}
