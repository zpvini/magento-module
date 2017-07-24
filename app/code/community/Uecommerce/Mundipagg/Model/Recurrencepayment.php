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
     * Armazena as informações passadas via formulário no frontend
     * @access public
     * @param array $data
     * @return Uecommerce_Mundipagg_Model_Standard
     */
    public function assignData($data)
    {
        $info = $this->getInfoInstance();
        $this->resetInterest($info);

        parent::assignData($data);

        $cctype1 = $data[$this->_code.'_1_1_cc_type'];

        if (isset($data[$this->_code.'_token_1_1']) && $data[$this->_code.'_token_1_1'] != 'new') {
            $parcelsNumber1 = $data[$this->_code.'_credito_parcelamento_1_1'];
            $cardonFile = Mage::getModel('mundipagg/cardonfile')->load($data[$this->_code.'_token_1_1']);
            $cctype1 = Mage::getSingleton('mundipagg/source_cctypes')->getCcTypeForLabel($cardonFile->getCcType());
            $value1 = $data[$this->_code.'_value_1_1'];
        } else {
            $parcelsNumber1 = $data[$this->_code.'_new_credito_parcelamento_1_1'];
            $value1 = $data[$this->_code.'_new_value_1_1'];
        }


        $interest = 0;
        $interestInformation = array();

        if(Mage::app()->getRequest()->getActionName() == 'partialPost'){
            $keyCode = $this->_code.'_partial';
            $interestInformation = $info->getAdditionalInformation('mundipagg_interest_information');
        }else{
            $keyCode = $this->_code;
        }

        if($cctype1) {
            $interest = Mage::helper('mundipagg/installments')->getInterestForCard($parcelsNumber1 , $cctype1, $value1);
            $interestInformation[$keyCode.'_1_1'] = new Varien_Object();
            $interestInformation[$keyCode.'_1_1']->setInterest(str_replace(',','.',$interest))->setValue(str_replace(',','.',$value1));
        }

        if ($interest > 0) {
            $info->setAdditionalInformation('mundipagg_interest_information', array());
            $info->setAdditionalInformation('mundipagg_interest_information',$interestInformation);
            $this->applyInterest($info, $interest);

        } else {
            // If none of Cc parcels doens't have interest we reset interest
            $this->resetInterest($info);
        }

        return $this;
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

        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();

        parent::authorize($payment, $order->getBaseTotalDue());
    }
}