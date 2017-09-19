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
 * @copyright  Copyright (c) 2015 Uecommerce (http://www.uecommerce.com.br/)
 * @license    http://www.uecommerce.com.br/
 */

/**
 * Mundipagg Payment module
 *
 * @category   Uecommerce
 * @package    Uecommerce_Mundipagg
 * @author     Uecommerce Dev Team
 */

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
        $discount = Uecommerce_Mundipagg_Helper_Installments::getDiscountOneInstallment($info->getQuote());
        foreach ($info->getQuote()->getAllAddresses() as $address) {
            $grandTotal = $address->getGrandTotal();
            if ($grandTotal) {
                $address->setMundipaggInterest($interest);
                $address->setGrandTotal($grandTotal - $discount + $interest);
                if ($discount) {
                    $address->setDiscountAmount(($address->getDiscountAmount() - $discount));
                    $address->setDiscountDescription(
                        $address->getDiscountDescription() . ' + ' . 
                        Mage::getStoreConfig('payment/mundipagg_recurrencepayment/recurrence_discount_message')
                    );
                }
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
