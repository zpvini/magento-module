<?php

class Uecommerce_Mundipagg_Test_Selenium_CcTypes_ThreecreditcardisTest extends Uecommerce_Mundipagg_Test_Selenium_CcTypes {

    public function setUp() {
        $this->_paymentType = 'threecreditcards';
        $this->_ccLength = 3;
        parent::setUp();
        $this->setCCValues($this->_ccLength);
        
    }
    
    public function testThreeCreditcardsRegistered() {
        $this->_isLogged = false;
        $this->runProcess();
    }
    
    /**
     * @depends testThreeCreditcardsRegistered
     */
    public function testThreeCreditcardsLogged(){
        $this->_isLogged = true;
        //$this->runCardonfile();
        $this->runProcess();
    }

    /**
     * @depends testThreeCreditcardsLogged
     */
    public function testThreeCreditcardsRegisteredPJ()
    {
        $this->_isLogged = false;
        $this->_isPj = true;
        $this->runProcess();
    }


}
