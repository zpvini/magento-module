<?php

class Uecommerce_Mundipagg_Test_Selenium_CcTypes_FourcreditcardisTest extends Uecommerce_Mundipagg_Test_Selenium_CcTypes {

    public function setUp() {
        $this->_paymentType = 'fourcreditcards';
        $this->_ccLength = 4;
        parent::setUp();
        $this->setCCValues($this->_ccLength);
        
    }
    
    public function testFourCreditcardsRegistered() {
        $this->_isLogged = false;
        $this->runProcess();
    }
    
    /**
     * @depends testFourCreditcardsRegistered
     */
    public function testFourCreditcardsLogged(){
        $this->_isLogged = true;
        $this->runProcess();
    }

    /**
     * @depends testFourCreditcardsLogged
     */
    public function testFourCreditcardsRegisteredPj()
    {
        $this->_isLogged = false;
        $this->_isPj = true;
        $this->runProcess();
    }

}
