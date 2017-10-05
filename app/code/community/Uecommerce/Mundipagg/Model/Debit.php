<?php

class Uecommerce_Mundipagg_Model_Debit extends Uecommerce_Mundipagg_Model_Standard
{
    /**
     * Availability options
     */
    protected $_code = 'mundipagg_debit';
    protected $_formBlockType = 'mundipagg/standard_debit';
    protected $_infoBlockType = 'mundipagg/info';
    protected $_isGateway = true;
    protected $_canOrder  = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;
    protected $_canSaveCc = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canManageRecurringProfiles = false;
    protected $_allowCurrencyCode = array('BRL', 'USD', 'EUR');
    protected $_isInitializeNeeded = true;

    public function __construct($Store = null)
    {
        if (!($Store instanceof Mage_Core_Model_Store)) {
            $Store = null;
        }
        parent::__construct($Store);
        switch ($this->getEnvironment()) {
            case 'localhost':
            case 'development':
            case 'staging':
            default:
                $environment = 'Staging';
                break;
            case 'production':
                $environment = 'Production';
                break;
        }
        $this->setUrl(trim($this->getConfigData('apiDebit'.$environment.'Url', $Store)));
        $this->setDebitTypes($this->getConfigData('debit_types', $Store));
    }
    
    /**
     * Armazena as informações passadas via formulário no frontend
     * @access public
     * @param array $data
     * @return Uecommerce_Mundipagg_Model_Standard
     */
    public function assignData($data)
    {
        $info = $this->getInfoInstance();
        $info->getQuote()->setTotalsCollectedFlag(false)->collectTotals();
        $info->getQuote()->preventSaving();
        $info = $this->resetInterest($info);
        $discount = Uecommerce_Mundipagg_Helper_Installments::getRecurrenceDiscount($info->getQuote());
        $interest = '';
        foreach ($info->getQuote()->getAllAddresses() as $address) {
            $grandTotal = $address->getGrandTotal();
            $messages = array();
            if ($address->getDiscountDescription()) {
                $messages[] = $address->getDiscountDescription();
            }
            if ($grandTotal) {
                $address->setMundipaggInterest($interest);
                $address->setGrandTotal($grandTotal - $discount + $interest);
                if ($discount) {
                    $address->setDiscountAmount(($address->getDiscountAmount() - $discount));
                    $msgRecurrence = Mage::getStoreConfig('payment/mundipagg_recurrencepayment/recurrence_discount_message');
                    if ($msgRecurrence) {
                        $messages[] = $msgRecurrence;
                    }
                }
            }
            if ($messages) {
                $address->setDiscountDescription(
                    implode(' + ', $messages)
                );
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
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();

        parent::order($payment, $order->getBaseTotalDue());
    }
}
