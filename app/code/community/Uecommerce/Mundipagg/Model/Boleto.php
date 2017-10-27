<?php

class Uecommerce_Mundipagg_Model_Boleto extends Uecommerce_Mundipagg_Model_Standard
{
    /**
     * Availability options
     */
    protected $_code = 'mundipagg_boleto';
    protected $_formBlockType = 'mundipagg/standard_boleto';
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
    protected $mundipaggDiscounts = null;

    public function __construct($Store = null)
    {
        if (!($Store instanceof Mage_Core_Model_Store)) {
            $Store = null;
        }
        parent::__construct($Store);
        
        $validadeBoleto = $this->getConfigData('dias_validade_boleto', $Store);
        
        if (empty($validadeBoleto) || $validadeBoleto == ' ' || is_null($validadeBoleto) || $validadeBoleto == '') {
            $validadeBoleto = '3';
        }
        $this->setDiasValidadeBoleto(trim($validadeBoleto));
        $this->setInstrucoesCaixa(trim($this->getConfigData('instrucoes_caixa', $Store)));
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

        $mundipaggDiscounts = $this->getMundipaggDiscounts($info->getQuote());

        foreach ($mundipaggDiscounts as $discount) {
            foreach ($info->getQuote()->getAllAddresses() as $address) {
                $grandTotal = $address->getGrandTotal();
                $messages = array();
                if ($grandTotal) {
                    $address->setMundipaggInterest(0);

                    if ($discount['pct']) {
                        $discount['value'] = $this->getDiscountValue($grandTotal, $discount['value']);
                    }
                    $totalWithDiscount = $grandTotal - $discount['value'];
                    $address->setGrandTotal($totalWithDiscount);
                    $address->setBaseGrandTotal($totalWithDiscount);

                    if (strlen($address->getDiscountDescription()) > 0) {
                        $messages[] = $address->getDiscountDescription();
                    }
                    $messages[] = $discount['description'];


                    $address->setDiscountDescription(
                        implode(' + ', $messages)
                    );
                    $totalDiscount = $address->getDiscountAmount() - $discount['value'];
                    $address->setDiscountAmount($totalDiscount);
                }
            }
        }
        parent::assignData($data);

        return $this;
    }

    private function getDiscountValue($grandTotal, $discountPercentual)
    {
        return $grandTotal * ($discountPercentual/100);
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
        $mageVersion = Mage::helper('mundipagg/version')->convertVersionToCommunityVersion(Mage::getVersion());

        if (version_compare($mageVersion, '1.5.0', '<')) {
            $orderAction = 'order';
        } else {
            $orderAction = Mage_Payment_Model_Method_Abstract::ACTION_ORDER;
        }

        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();

        parent::order($payment, $order->getBaseTotalDue());
    }

    private function getMundipaggDiscounts($quote)
    {
        //Recurrence
        $this->getMundipaggDiscountArray(
            Uecommerce_Mundipagg_Helper_Installments::getRecurrenceDiscount($quote),
            Uecommerce_Mundipagg_Helper_Installments::getRecurrenceDiscountMessage()
        );

        //Boleto
        $this->getMundipaggDiscountArray(
            Mage::getStoreConfig('payment/mundipagg_boleto/boleto_discount_value'),
            Mage::getStoreConfig('payment/mundipagg_boleto/boleto_discount_message'),
            true
        );

        return $this->mundipaggDiscounts;
    }

    private function getMundipaggDiscountArray($discount, $description, $pct = false)
    {
        if ($discount > 0 && $discount !== '') {
            $this->mundipaggDiscounts[] = array(
                'value' => (float) $discount,
                'description' => $description,
                'pct' => $pct
            );
        }
    }
}
