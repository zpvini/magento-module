<?php

class Uecommerce_Mundipagg_Model_Quote_Address_Interest extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    /**
     * Constructor that should initialize
     */
    public function __construct()
    {
        $this->setCode('mundipagg_interest');
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        if ($address->getMundipaggInterest() != 0) {
            $address->addTotal(array
            (
                'code' => $this->getCode(),
                'title' => Mage::helper('mundipagg')->__('Interest'),
                'value' => $address->getMundipaggInterest()
            ));
            $this->collect($address);
        }
    }
}
