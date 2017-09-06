<?php

class Uecommerce_Mundipagg_Model_Quote_Address_Recurrence extends Mage_Sales_Model_Quote_Address_Total_Abstract {

    public function __construct()
    {
        $this->setCode('mundipagg_recurrence');
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $quote = $address->getQuote();
        $baseGrandTotal = $address->getBaseGrandTotal();
        $payment = $quote->getPayment()->getMethod();
        $msg = Mage::getStoreConfig('payment/mundipagg_recurrencepayment/recurrent_mix_message');
        $items = $quote->getAllItems();
        if(
            $payment == 'mundipagg_recurrencepayment' && 
            $this->checkItemAlone($items) &&
            $this->checkRecurrenceMix($items) 
        ) {
            $address->addTotal(array
            (
                'code' => 'mundipagg_recurrence',
                'title' => $msg,
                'value' => $baseGrandTotal
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


    /**
     * Check if exists a recurrence mix product in $items
     * @param array $items Cart items
     * @return boolean
     */
    private function checkRecurrenceMix($items) 
    {
        
        foreach ($items as $item) {
            foreach ($item->getOptions() as $option) {
                $product = $option->getProduct();
                $product->load($product->getId());
                if ($product->getMundipaggRecurrenceMix() === '1') {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Check if exists only on product in $items
     * @return boolean
     */
    private function checkItemAlone($items) 
    {
        
        $countItems = count($items);
        if ($countItems > 1) {
            return false;
        }
        foreach ($items as $item) {
            foreach ($item->getOptions() as $option) {
                $product = $option->getProduct();
                $product->load($product->getId());
                $productQty = $item->getQty();
                if (
                    $productQty > 1
                ) {
                    return false;
                }
                return true;
            }
        }
    }
}
