<?php

class Uecommerce_Mundipagg_Model_Creditcard extends Uecommerce_Mundipagg_Model_Standard
{
    /**
     * Availability options
     */
    protected $_code = 'mundipagg_creditcard';
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
    protected $_allowCurrencyCode = ['BRL', 'USD', 'EUR'];
    protected $_isInitializeNeeded = true;

    /**
     * Armazena as informações passadas via formulário no frontend
     * @access public
     * @param array $data
     * @return Uecommerce_Mundipagg_Model_Standard
     * @throws Varien_Exception
     */
    public function assignData($data)
    {
        if (
            isset($data[$this->_code.'_token_1_1']) &&
            $data[$this->_code.'_token_1_1'] != 'new'
        ) {
            $parcelsNumber = $data[$this->_code.'_credito_parcelamento_1_1'];
            $cardonFile = Mage::getModel('mundipagg/cardonfile')
                ->load($data[$this->_code.'_token_1_1']);
            $cctype = Mage::getSingleton('mundipagg/source_cctypes')
                ->getCcTypeForLabel($cardonFile->getCcType());
        } else {
            $cctype = $data[$this->_code.'_1_1_cc_type'];
            $parcelsNumber = $data[$this->_code.'_new_credito_parcelamento_1_1'];
        }

        $info = $this->getInfoInstance();
        $info->getQuote()->setTotalsCollectedFlag(false)->collectTotals();
        $info = $this->resetInterest($info);

        $discount = Uecommerce_Mundipagg_Helper_Installments::getRecurrenceDiscount(
            $info->getQuote()
        );
        $interest = Mage::helper('mundipagg/installments')
            ->getInterestForCard(
                $parcelsNumber,
                $cctype,
                $info->getQuote()->getGrandTotal() - $discount
            );

        if ($interest > 0) {
            $interestInformation = [];
            $interestInformation[$this->_code.'_1_1'] = new Varien_Object();
            $interestInformation[$this->_code.'_1_1']
                ->setInterest(str_replace(
                    ',',
                    '.',
                    $interest)
                );
            $info->setAdditionalInformation(
                'mundipagg_interest_information',
                []
            );

            $info->setAdditionalInformation(
                'mundipagg_interest_information',
                $interestInformation
            );
            $this->applyInterest($info, $interest);
        } else {
            // If none of Cc parcels doens't have interest we reset interest
            $info = $this->resetInterest($info);
        }

        $discount = Uecommerce_Mundipagg_Helper_Installments::getRecurrenceDiscount(
            $info->getQuote()
        );

        foreach ($info->getQuote()->getAllAddresses() as $address) {
            $grandTotal = $address->getGrandTotal();
            $messages = [];
            if ($address->getDiscountDescription()) {
                $messages[] = $address->getDiscountDescription();
            }
            if ($grandTotal) {
                $address->setMundipaggInterest($interest);
                $address->setGrandTotal($grandTotal - $discount + $interest);
                if ($discount) {
                    $address->setDiscountAmount(
                        ($address->getDiscountAmount() - $discount)
                    );
                    $msgRecurrence = Mage::getStoreConfig(
                        'payment/mundipagg_recurrencepayment/recurrence_discount_message'
                    );
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
        return parent::assignData($data);
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
     * @param $paymentAction
     * @param $stateObject
     * @throws Varien_Exception
     */
    public function initialize($paymentAction, $stateObject)
    {
        $standard = Mage::getModel('mundipagg/standard');
        $paymentAction = $standard->getConfigData('payment_action');

        $antifraudHelper = Mage::helper('mundipagg/antifraud');
        $minValue = $antifraudHelper->getMinimumValue();

        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $baseAmountOrder = $payment->getBaseAmountOrdered();
        $antiFraudEnabled = $this->getAntiFraud();

        $helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
        $helperLog->setLogLabel('|' . $order->getIncrementId() . '|');
        $helperLog->info('initialize()');

        if (
            $antiFraudEnabled &&
            $standard->getConfigData('payment_action') == 'order' &&
            ($baseAmountOrder > $minValue)
        ) {
            $paymentAction = 'authorize';
        }

        switch ($paymentAction) {
            case 'order':
                $this->setCreditCardOperationEnum(
                    'AuthAndCapture'
                );

                $paymentAction = $orderAction = 'order';
                break;

            case 'authorize':
                $this->setCreditCardOperationEnum(
                    'AuthOnly'
                );

                $paymentAction = $orderAction = 'authorize';
                break;

            case 'authorize_capture':
                $this->setCreditCardOperationEnum(
                    'AuthAndCaptureWithDelay'
                );

                $paymentAction = $orderAction = 'authorize_capture';
                break;
        }

        $helperLog->info('Payment method: CreditCard');
        $helperLog->info('Payment action: ' . $paymentAction);

        switch ($paymentAction) {
            case Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE:
                parent::authorize($payment, $order->getBaseTotalDue());
                break;

            case Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE:
                parent::authorize($payment, $order->getBaseTotalDue());
                break;

            case $orderAction:
                parent::order($payment, $order->getBaseTotalDue());
                break;

            default:
                parent::order($payment, $order->getBaseTotalDue());
                break;
        }
    }
}
