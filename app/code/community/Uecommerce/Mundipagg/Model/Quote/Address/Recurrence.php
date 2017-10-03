<?php

class Uecommerce_Mundipagg_Model_Quote_Address_Recurrence extends Mage_Sales_Model_Quote_Address_Total_Abstract
{

    public function __construct()
    {
        $this->setCode('mundipagg_recurrence');
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $quote = $address->getQuote();
        $payment = $quote->getPayment()->getMethod();
        $items = $quote->getAllItems();
        if ($address->getGrandTotal() &&
            $payment == 'mundipagg_recurrencepayment' &&
            Uecommerce_Mundipagg_Model_Observer::checkItemAlone($quote) &&
            Uecommerce_Mundipagg_Model_Observer::checkRecurrenceMix($quote)
        ) {
            foreach ($items as $item) {
                $product = $item->getProduct();
                if ($product->getMundipaggRecurrent()) {
                    $frequency = $product->getMundipaggFrequencyEnum();
                    $interval = $product->getMundipaggRecurrences();
                    break;
                }
            }
            $msg = Mage::getStoreConfig('payment/mundipagg_recurrencepayment/recurrent_mix_message');
            $freq = strtolower(Mage::helper('mundipagg')->__($frequency));
            $msg .= ' ' . $freq . ' (' . $interval . 'x)';
            $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
            $quote = Mage::getModel("sales/quote")->load($quoteId);
            $address->addTotal(array(
                'code' => 'mundipagg_recurrence',
                'title' => $msg,
                'value' => round($quote->getGrandTotal() / $interval, 2, PHP_ROUND_HALF_DOWN)
            ));
        }
    }
    
    private function getRecurrenceValue($quote, $baseGrandTotal)
    {
        $items = $quote->getAllItems();
        
        if (isset($items[0])) {
            $product = $items[0]->getProduct();
            $recurrences = $product->getMundipaggRecurrences();
            return $baseGrandTotal/$recurrences;
        }
        return;
    }
}
