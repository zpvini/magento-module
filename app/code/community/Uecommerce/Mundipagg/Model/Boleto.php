<?php
/**
 * Uecommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Uecommerce EULA.
 * It is also available through the world-wide-web at this URL:
 * http://www.uecommerce.com.br/
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the extension
 * to newer versions in the future. If you wish to customize the extension
 * for your needs please refer to http://www.uecommerce.com.br/ for more information
 *
 * @category   Uecommerce
 * @package    Uecommerce_Mundipagg
 * @copyright  Copyright (c) 2012 Uecommerce (http://www.uecommerce.com.br/)
 * @license    http://www.uecommerce.com.br/
 */

/**
 * Mundipagg Payment module
 *
 * @category   Uecommerce
 * @package    Uecommerce_Mundipagg
 * @author     Uecommerce Dev Team
 */

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

    public function __construct($Store = null)
    {
        if (!($Store instanceof Mage_Core_Model_Store)) {
            $Store = null;
        }
        parent::__construct($Store);
        
        $validadeBoleto = $this->getConfigData('dias_validade_boleto', $Store);
        
        if(empty($validadeBoleto) || $validadeBoleto == ' ' || is_null($validadeBoleto) || $validadeBoleto == ''){
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

        $discount = Uecommerce_Mundipagg_Helper_Installments::getDiscountOneInstallment($info->getQuote());

        $boletoDiscount = $this->getShoppingCartRulesBoletoDiscount();
        if ($boletoDiscount) {
            $session = Mage::getSingleton('checkout/session');
            $session->setData('boleto_promo_discount', $boletoDiscount['value']);
        }

        foreach ($info->getQuote()->getAllAddresses() as $address) {
            $grandTotal = $address->getGrandTotal();
            if ($grandTotal) {
                $address->setMundipaggInterest(0);
                $address->setGrandTotal($grandTotal - $discount);
                if ($discount) {
                    $address->setDiscountAmount(($address->getDiscountAmount() - $discount));
                    $address->setDiscountDescription(
                        $address->getDiscountDescription() . ' + ' .
                        Mage::getStoreConfig('payment/mundipagg_recurrencepayment/recurrence_discount_message')
                    );
                }

                if ($boletoDiscount) {
                    $address->setDiscountAmount($boletoDiscount['value'] * -1);
                    $address->setDiscountDescription($boletoDiscount['message']);
                }
            }
        }

        parent::assignData($data);

        return $this;
    }

    private function getShoppingCartRulesBoletoDiscount()
    {
        $appliedRuleIds = Mage::getSingleton('checkout/session')->getQuote()->getAppliedRuleIds();
        $discount = array();

        foreach (explode(',', $appliedRuleIds) as $ruleId) {
            $rule = Mage::getModel('salesrule/rule')->load($ruleId);

            $discount = $this->getBoletoDiscountFromRule($rule);
            if ($discount) {
                break;
            }
        }

        return $discount;
    }

    private function getBoletoDiscountFromRule($rule)
    {
        $conditions = unserialize($rule->getConditionsSerialized())['conditions'];
        $conditionsInfo = $conditions[0];

        if (count($conditions) != 1 || $conditionsInfo['value'] !== 'mundipagg_boleto') {
            return array();
        }

        $quote = Mage::getModel('checkout/session')->getQuote();
        $quoteData = $quote->getData();
        $subtotal = $quoteData['subtotal'];

        $ruleData = $rule->getData();
        $percent = $ruleData['discount_amount'];

        return array(
            'value' => (floatval($percent)/100) * $subtotal,
            'message' => $ruleData['name']
        );
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
}