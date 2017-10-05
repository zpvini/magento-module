<?php

class Uecommerce_Mundipagg_Test_Selenium_CcTypes_FivecreditcardisTest extends Uecommerce_Mundipagg_Test_Selenium_CcTypes
{

    public function setUp()
    {
        $this->_paymentType = 'fivecreditcards';
        $this->_ccLength = 5;
        parent::setUp();
        $this->setCCValues($this->_ccLength);
    }
    
    public function testFiveCreditcardsRegistered()
    {
        $this->_isLogged = false;
        $this->runProcess();
    }
    
    /**
     * @depends testFiveCreditcardsRegistered
     */
    public function testFiveCreditcardsLogged()
    {
        $this->_isLogged = true;
        $this->runProcess();
    }

    public function testFiveCreditcardsRegisteredPj()
    {
        $this->_isLogged = false;
        $this->_isPj = true;
        $this->runProcess();
    }
}
