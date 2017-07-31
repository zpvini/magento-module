<?php

class Uecommerce_Mundipagg_Helper_Recurrence extends Mage_Core_Helper_Abstract {
    public function teste() {
        $session = Mage::getSingleton('admin/session');

		if ($session->isLoggedIn()) {
			$quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
		} else {
			$quote = (Mage::getModel('checkout/type_onepage') !== false) ? Mage::getModel('checkout/type_onepage')->getQuote() : Mage::getModel('checkout/session')->getQuote();
		}

        // Get pre-authorized amount
        $authorizedAmount = Mage::getSingleton('checkout/session')->getAuthorizedAmount();
        $amount = (double)$quote->getGrandTotal() - $quote->getMundipaggInterest() - $authorizedAmount;

        $result = array();
		$amount = str_replace(',', '.', $amount);

        $items = $quote->getAllItems();
        $recurrent = false;
        foreach ($items as $item) {

            foreach ($item->getOptions() as $option) {
                $product = $option->getProduct();
                $product->load($product->getId());

                if ($product->getMundipaggRecurrent()) {
                    $recurrent = true;
                    $frequency = $product->getMundipaggFrequencyEnum();
                    $interval = $product->getMundipaggRecurrences();
                    $recurrenceMix = $product->getMundipaggRecurrenceMix();
                }
            }
        }
        
    }
}