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
class Uecommerce_Mundipagg_Model_Recurrency extends Varien_Object {

    /**
     * Loaded Product
     * 
     * @var Mage_Catalog_Model_Product
     */
    protected $_product;

    /**
     * Recurrency for this Product
     * 
     * @var array
     */
    protected $_recurrency;

    /**
     * Recurrences for all Products
     * 
     * @var array
     */
    protected $_recurrencesData;

    /**
     * Get item from quote
     * 
     * @var Mage_Sales_Model_Order_Item
     */
    protected $_item;

    /**
     * Varien_Object
     */
    public function _construct() {
        parent::_construct();

        $this->_recurrences = array();
        $this->_product = null;
        $this->_recurrency = null;
        $this->_recurrencesData = array();
    }

    /**
     * Set item
     * 
     * @param Mage_Sales_Model_Order_Item $item
     * @return Uecommerce_Mundipagg_Model_Recurrency
     */
    public function setItem(Mage_Sales_Model_Order_Item $item) {
        $this->_item = $item;
        
        $this->_product = Mage::getModel('catalog/product')->load($item->getProductId());


        if ($this->_product->getMundipaggRecurrent() && $this->isRecurrent()) {
            $this->_setRecurrencyByProduct($this->_product);
        }

        return $this;
    }

    /**
     * Set Product
     * 
     * @param Mage_Catalog_Model_Product $product
     * @return Uecommerce_Mundipagg_Model_Recurrency
     */
    public function setProduct(Mage_Catalog_Model_Product $product) {
        if ($product->HasMundipaggRecurrent()) {
            $this->_product = $product;
        } else {
            $this->_product = Mage::getModel('catalog/product')->load($product->getId());
        }

        if ($this->_product->getMundipaggRecurrent() && $this->isRecurrent()) {
            $this->_setRecurrencyByProduct($this->_product);
        }

        return $this;
    }

    /**
     * Set Product by id
     * 
     * @param int $id
     * @return Uecommerce_Mundipagg_Model_Recurrency
     */
    public function setProductById($id) {
        $this->_product = Mage::getModel('catalog/product')->load($id);

        if ($this->_product->getMundipaggRecurrent() && $this->isRecurrent()) {
            $this->_setRecurrencyByProduct($this->_product);
        }

        return $this;
    }

    /**
     * Check if this product is recurrent
     * 
     * @return boolean
     */
    public function isRecurrent() {
        return $this->getProduct()->getMundipaggRecurrent() ? true : false;
    }

    /**
     * Get product
     * 
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct() {
        return $this->_product;
    }

    /**
     * Set Recurrency for this product
     * 
     * @param Mage_Catalog_Model_Product $_product
     * @return boolean
     */
    protected function _setRecurrencyByProduct(Mage_Catalog_Model_Product $_product) {
        if (!$_product->getMundipaggRecurrent()) {
            return false;
        }

        switch ($_product->getMundipaggFrequencyEnum()) {
            case 'Quarterly':
                $frequency = 'Monthly';
                $interval = '3';
                break;
            case 'Biannual':
                $frequency = 'Monthly';
                $interval = '6';
                break;
            default:
                $frequency = $_product->getMundipaggFrequencyEnum();
                $interval = '1';
        }

        $this->_recurrency = array(
            'DateToStartBilling' => $this->getFormattedDateToStartBilling($frequency, $interval),
            'Frequency' => $frequency,
            'Interval' => $interval,
            'Recurrences' => ($_product->getMundipaggRecurrences() - 1)
        );

        $this->setData('recurrency', $this->_recurrency);

        $this->addRecurrencyData();

        return true;
    }

    /**
     * Get Formatted Date to Start Billing
     * 
     * @param string $frequency
     * @param int $interval
     * @return string
     */
    public function getFormattedDateToStartBilling($frequency, $interval) {
        $date = new Zend_Date(Mage::getModel('core/date')->timestamp(), Zend_Date::TIMESTAMP);

        switch ($frequency) {
	        case '0':
	        	$frequency = null;
	        	break;

            case 'Daily':
                $frequency = 'Day';
                break;
            case 'Weekly':
                $frequency = 'Week';
                break;
            case 'Monthly':
                $frequency = 'Month';
                break;
            case 'Yearly':
                $frequency = 'Year';
                break;
        }

        $function = 'add' . $frequency;

	    if(is_null($frequency) === false){
		    $date->{$function}($interval);
	    }

        return $date->toString('yyyy-MM-ddTHH:mm:ss');
    }

    /**
     * Get item from Order
     * 
     * @return Mage_Sales_Model_Order_Item
     */
    public function getItem() {
        return $this->_item;
    }

    /**
     * Get the item price with the discount and tax applied
     * 
     * @return float
     */
    public function getItemFinalPrice() {
        $item = $this->getItem();

        $amount = $item->getPrice();
        if ($item->getDiscountAmount()) {
            $amount = ($amount - $item->getDiscountAmount());
        }

        if ($item->getTaxAmount()) {
            $amount = ($amount + $item->getTaxAmount());
        }

        return $amount;
    }

    /**
     * Add current recorrency in array data.
     */
    protected function addRecurrencyData() {
    	$recurrencyRef = $this->_recurrency;

        if (!empty($recurrencyRef)) {
            $recurrency = new Varien_Object();
            $recurrency->setData('product', $this->getProduct());
            $recurrency->setData('recurrency', $this->getRecurrency());
            $recurrency->setData('item', $this->getItem());
            $recurrency->getItem()->setItemFinalPrice($this->getItemFinalPrice());

            $this->_recurrencesData[] = $recurrency;
        }
    }

    /**
     * Get all recurrences Data
     * 
     * @return array 
     */
    public function getRecurrencesData() {
        return $this->_recurrencesData;
    }

    /**
     * Check if there is any recurrency
     * 
     * @return boolean 
     */
    public function recurrencyExists() {
        $recurrencesData = $this->getRecurrencesData();

        if (!empty($recurrencesData)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Generate all recurrences in request if exists
     * 
     * @param array $_request
     * @param int $installmentCount
     * @return array Parameter $_request
     */
    public function generateRecurrences($_request, $installmentCount) {
        if (!$this->recurrencyExists()) {
            return $_request;
        }

        foreach ($this->getRecurrencesData() as $recurrency) {
            $newCreditCardTransactionCollection = $_request['CreditCardTransactionCollection'][0];

            if ($recurrency->hasItem()) {
                $amountItem = $recurrency->getItem()->getItemFinalPrice();

                $amount = str_replace('.', '', number_format($amountItem, 2, '.', ''));
            } else {
                $amount = str_replace('.', '', number_format($recurrency->getProduct()->getFinalPrice(), 2, '.', ''));
            }

            $itemRecurrency = $recurrency->getRecurrency();
            $newCreditCardTransactionCollection['AmountInCents'] = $amount;
            $newCreditCardTransactionCollection['InstallmentCount'] = $installmentCount;
            $newCreditCardTransactionCollection['Recurrency'] = $itemRecurrency;
            $newCreditCardTransactionCollection['CreditCardOperation'] = 'AuthOnly';
            $_request['CreditCardTransactionCollection'][] = $newCreditCardTransactionCollection;
        }
        
        return $_request;
    }
    
    /**
     * 
     * @param Mage_Sales_Model_Order $order
     * @return boolean
     */
    public function checkRecurrencesByOrder(Mage_Sales_Model_Order $order){
        $payment = $order->getPayment();
        if($payment->getAdditionalInformation('isRecurrency') != '1'){
            return false;
        }
        
        // @var $transactions Mage_Sales_Model_Order_Payment_Transaction
        $transactions = Mage::getModel('sales/order_payment_transaction')->getCollection()
                ->addAttributeToFilter('order_id', array('eq' => $order->getEntityId()));
        $transactionsKeys = array();
        if($transactions->getSize()){
            foreach($transactions as $transaction){
                $transactionsKeys[] = $transaction->getAdditionalInformation('TransactionKey');
            }
        }
        
        // @var $api Uecommerce_Mundipagg_Model_Api
        $api = Mage::getModel('mundipagg/api');
        
        $apiTransactions = $api->getTransactionHistory($payment->getAdditionalInformation('OrderKey'));
        
        $transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;
        
        if(count($apiTransactions['result'])){
            foreach($apiTransactions['result']->SaleDataCollection->Sale->CreditCardTransactionDataCollection->CreditCardTransactionData as $transaction){
                if(!in_array($transaction->TransactionKey, $transactionsKeys)){
                    // Check if ONEDOLLARAUTH transaction
                    if($transaction->AmountInCents == '100' 
                            && $transaction->AuthorizedAmountInCents == '100'
                            && $transaction->InstallmentCount == '0'
                            && $transaction->IsReccurency == 'true'){
                        continue;
                    }
                    $this->_addTransaction($payment, $transaction->TransactionKey, $transactionType, $transaction);
                }
            }
        }
        
    }
    
    /**
     * Add payment transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param string $transactionId
     * @param string $transactionType
     * @param array $transactionAdditionalInfo
     * @return null|Mage_Sales_Model_Order_Payment_Transaction
     */
    public function _addTransaction(Mage_Sales_Model_Order_Payment $payment, $transactionId, $transactionType, $transactionAdditionalInfo) 
    {
        $transaction = Mage::getModel('sales/order_payment_transaction');
        $transaction->setOrderPaymentObject($payment);
        
        $transaction = $transaction->loadByTxnId($transactionId.'-'.$transactionType);

        $transaction->setTxnType($transactionType);
        $transaction->setTxnId($transactionId.'-'.$transactionType);

        if($transactionType == 'authorization') {
            if ($transactionAdditionalInfo['CreditCardTransactionStatus'] == 'AuthorizedPendingCapture') {
                $transaction->setIsClosed(0);
            }

            if ($transactionAdditionalInfo['CreditCardTransactionStatus'] == 'NotAuthorized') {
                $transaction->setIsClosed(1);
            }
        }

        /*
        if ($transactionAdditionalInfo['Success'] == true && ($transactionType == 'order' || $transactionType == 'payment')) {
            $transaction->setIsClosed(1);
        }
        */
        
        foreach ($transactionAdditionalInfo as $transKey => $value) {
            if (!is_array($value)){
                $transaction->setAdditionalInformation($transKey, htmlspecialchars_decode($value));
            } else {
                foreach ($value as $key2 => $value2) {
                    $transaction->setAdditionalInformation($key2, htmlspecialchars_decode($value2));
                }
            }
        }

        return $transaction->save(); 
    }

}
