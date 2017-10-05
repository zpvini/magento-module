<?php


class Uecommerce_Mundipagg_Test_Selenium_BoletoTest extends Uecommerce_Mundipagg_Test_Selenium_Abstract
{

    public function setUp()
    {
        $this->_installmentActive=false;
        $this->_additionalSaveSettings['payment/mundipagg_boleto/active'] = '1';
        parent::setUp();
    }
    
   /**
    * Test boleto registering a new customer.
    */
    public function testBoletoRegistered()
    {
        $this->_isLogged = false;
        $this->runMundipagg();
        $this->setBoleto();
    }

    /**
     * Test boleto registering a new company customer
     * @depends testBoletoLogged
     */
    public function testBoletoRegisteredPJ()
    {
        $this->_isLogged = false;
        $this->_isPj = true;
        $this->setBoleto();
    }
    
    /**
     * Test boleto with the logged customer.
     *
     * @depends testBoletoRegistered
     */
    public function testBoletoLogged()
    {
        $this->_isLogged = true;
        $this->runMundipagg();
        $this->setBoleto();
    }

    /**
     * Test boleto with the logged company customer.
     *
     * @depends testBoletoRegisteredPJ
     */
    public function testBoletoLoggedPj()
    {
        $this->_isLogged = true;
        $this->_isPj = true;
        $this->runMundipagg();
        $this->setBoleto();
    }
    
    /**
     * Set all values to boleto and test.
     */
    protected function setBoleto()
    {
        $customer = $this->getCustomer();
        $this->clickButtonByContainer('shipping-method-buttons-container');
        sleep(self::$_defaultSleep);
        $this->byId('p_method_mundipagg_boleto')->click();
        $this->byId('mundipagg_boleto_boleto_taxvat')->value($customer['taxvat']);
        $this->clickButtonByContainer('payment-buttons-container');
        sleep(self::$_defaultSleep);
        $this->clickButtonByContainer('review-buttons-container');
        sleep(self::$_defaultSleep);

        if (!getenv($this->_envCI)) {
            $elements = $this->byCssSelector('.pagSucess')->elements($this->using('css selector')->value('a'));
            foreach ($elements as $element) {
                $element->click();
            }
            sleep(self::$_defaultSleep+20);
            $this->assertContains('mundipagg/standard/success', $this->url());
        }
    }
}
