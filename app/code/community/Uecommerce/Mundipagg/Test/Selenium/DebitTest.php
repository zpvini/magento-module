<?php


class Uecommerce_Mundipagg_Test_Selenium_DebitTest extends Uecommerce_Mundipagg_Test_Selenium_Abstract
{
    //001,237,341,VBV,cielo_mastercard,cielo_visa
    protected $_debitTypes = array(
        '001',
        //'237', // @todo O banco especificado não está configurado para a loja Uecommerce
        '341',
        //'VBV', // @todo O banco especificado não está configurado para a loja Uecommerce
        'cielo_mastercard',
        'cielo_visa'
    );

    protected $_randonBanck = false;

    public function setUp()
    {
        $this->_installmentActive = false;
        $this->_additionalSaveSettings['payment/mundipagg_debit/active'] = '1';
        $this->_additionalSaveSettings['payment/mundipagg_debit/debit_types'] = implode(',',$this->_debitTypes);
        $this->_additionalSaveSettings['payment/mundipagg_debit/apiDebitStagingUrl'] = 'https://32616eb84cb7487a81b748ab2eeeeac5.cloudapp.net/Order/OnlineDebit/';
        $this->_additionalSaveSettings['payment/mundipagg_standard/merchantKeyStaging'] = '41CAA365-1A75-4FB7-BF1B-2EAA089264DB';

        parent::setUp();
    }

    /**
     * Test debit registering a new customer.
     */
    public function testDebitRegistered(){
        $this->_isLogged = false;
        $this->runMundipagg();
        $this->setDebit();
    }

    /**
     * @depends testDebitRegistered
     */
    public function testDebitLogged()
    {
        $this->_isLogged = true;
        $this->runMundipagg();
        $this->setDebit();
    }

    /**
     * @depends testDebitLogged
     */
    public function testDebitRegisteredPj()
    {
        $this->_isPj = true;
        $this->_isLogged = false;
        $this->runMundipagg();
        $this->setDebit();
    }

    /**
     * Set all values to debit and test.
     */
    protected function setDebit(){
        $customer = $this->getCustomer();
        $this->clickButtonByContainer('shipping-method-buttons-container');
        sleep(self::$_defaultSleep);
        $this->byId('p_method_mundipagg_debit')->click();
        $this->byId('mundipagg_debit_taxvat')->value($customer['taxvat']);
        sleep(1);
        $this->byId('mundipagg_debit_'.$this->getRandonBanck())->click();
        $this->clickButtonByContainer('payment-buttons-container');
        sleep(self::$_defaultSleep);
        $this->clickButtonByContainer('review-buttons-container');
        sleep(self::$_defaultSleep);
        switch ($this->getRandonBanck()) {
            case 'cielo_visa':
            case 'cielo_mastercard':
                $url = 'cieloecommerce.cielo.com.br';
                break;
            case '341':
                $url = 'onlinedebitstaging.mundipaggone.com';
                break;
            case '001':
                $url = 'www16.bancodobrasil.com.br';
                break;
        }
        $this->execSuccessTest($url);
    }

    protected function getRandonBanck()
    {
        if(!$this->_randonBanck) {
            $this->_randonBanck = $this->_debitTypes[array_rand($this->_debitTypes)];
        }

        return $this->_randonBanck;
    }

}