<?php

class Uecommerce_Mundipagg_Test_Selenium_CcTypes_CreditcardoneinstallmentTest extends Uecommerce_Mundipagg_Test_Selenium_CcTypes {

    public function setUp() {
        $this->_paymentType = 'creditcardoneinstallment';
        parent::setUp();
        
    }
    
    

    public function testCreditcardoneinstallmentRegistered() {
        $this->_isLogged = false;
        $this->runProcess();
    }
    
    /**
     * @depends testCreditcardoneinstallmentRegistered
     */
    public function testCreditcardoneinstallmentLogged(){
        $this->_isLogged = true;
        //$this->runCardonfile();
        $this->runProcess();
    }

    /**
     * @depends  testCreditcardoneinstallmentLogged
     */
    public function testCreditcardoneinstallmentRegisteredPj()
    {
        $this->_isLogged = false;
        $this->_isPj = true;
        $this->runProcess();
    }

}
