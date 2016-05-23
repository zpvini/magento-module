<?php

class Uecommerce_Mundipagg_Test_Selenium_CcTypes_TwocreditcardsTest extends Uecommerce_Mundipagg_Test_Selenium_CcTypes {

    public function setUp() {
        $this->_paymentType = 'twocreditcards';
        $this->_ccLength = 2;
        parent::setUp();
        $this->setCCValues($this->_ccLength);
    }
    
    public function testTwoCreditcardsRegistered() {
        $this->_isLogged = false;
        $this->runProcess();
    }
    
    /**
     * @depends testTwoCreditcardsRegistered
     */
    public function testTwoCreditcardsLogged(){
        $this->_isLogged = true;
        //$this->runCardonfile();
        $this->runProcess();
    }

    /**
     * @depends testTwoCreditcardsLogged
     */
    public function testTwoCreditcardsRegisteredPj()
    {
        $this->_isLogged = false;
        $this->_isPj = true;
        $this->runProcess();
    }



}
