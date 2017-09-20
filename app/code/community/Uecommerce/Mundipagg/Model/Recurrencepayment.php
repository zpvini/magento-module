<?php

class Uecommerce_Mundipagg_Model_RecurrencePayment extends Uecommerce_Mundipagg_Model_Standard
{
   /**
     * Availability options
     */
    protected $_code = 'mundipagg_recurrencepayment';
    protected $_formBlockType = 'mundipagg/standard_form';
    protected $_infoBlockType = 'mundipagg/info';
    protected $_isGateway = true;
    protected $_canOrder  = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;
    protected $_canSaveCc = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canManageRecurringProfiles = false;
    protected $_allowCurrencyCode = array('BRL', 'USD', 'EUR');
    protected $_isInitializeNeeded = true;

    /**
     * @access public
     * @param array $data
     * @return Uecommerce_Mundipagg_Model_Standard
     */
    public function assignData($data)
    {
        if (
            isset($data[$this->_code.'_token_1_1']) &&
            $data[$this->_code.'_token_1_1'] != 'new'
        ) {
            $cardonFile = Mage::getModel('mundipagg/cardonfile')->load($data[$this->_code.'_token_1_1']);
            $cctype = Mage::getSingleton('mundipagg/source_cctypes')->getCcTypeForLabel($cardonFile->getCcType());
        } else {
            $cctype = $data[$this->_code.'_1_1_cc_type'];
        }

        $info = $this->getInfoInstance();
        $info->getQuote()->setTotalsCollectedFlag(false)->collectTotals();
        $info->getQuote()->preventSaving();
        $info = $this->resetInterest($info);

        $interest = Mage::helper('mundipagg/installments')->getInterestForCard(1 , $cctype);

        if ($interest > 0) {
            $interestInformation = array();
            $interestInformation[$this->_code.'_1_1'] = new Varien_Object();
            $interestInformation[$this->_code.'_1_1']->setInterest(str_replace(',','.',$interest));
            $info->setAdditionalInformation('mundipagg_interest_information', array());
            $info->setAdditionalInformation('mundipagg_interest_information',$interestInformation);
            $this->applyInterest($info, $interest);
        } else {
            // If none of Cc parcels doens't have interest we reset interest
            $info = $this->resetInterest($info);
        }
        foreach ($info->getQuote()->getAllAddresses() as $address) {
            $grandTotal = $address->getGrandTotal();
            if ($grandTotal) {
                $items = $info->getQuote()->getAllItems();
                $frequency = 1;
                foreach($items as $item) {
                    $product = $item->getProduct();
                    if ($product->getMundipaggRecurrent()) {
                        $frequency = $product->getMundipaggFrequencyEnum();
                        $interval = $product->getMundipaggRecurrences();
                        break;
                    }
                }
                $address->setMundipaggInterest($interest);
                $address->setGrandTotal(($grandTotal + $interest) / $interval);
            }
        }

        parent::assignData($data);
    }

    /**
     * Prepare info instance for save
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function prepareSave()
    {
        parent::prepareSave();
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject)
    {
        $this->setCreditCardOperationEnum('AuthAndCapture');
        parent::order($this->getInfoInstance(), 0);
    }
}