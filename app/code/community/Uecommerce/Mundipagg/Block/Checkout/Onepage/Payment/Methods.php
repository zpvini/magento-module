<?php

class Uecommerce_Mundipagg_Block_Checkout_Onepage_Payment_Methods extends Mage_Checkout_Block_Onepage_Payment_Methods
{
    protected function _construct()
    {
        $payment = $this->getRequest()->getParam('payment');
        $session = Mage::getSingleton('checkout/session');
        $paymentMethod = $session->getData('payment_method_in_session');

        if (isset($payment['method'])) {
            // If payment method in request is different from the value set in the session
            if ($paymentMethod != $payment['method']) {
                // Reset payment
                $this->setPaymentMethod('');
                // Set new payment method
                $this->setPaymentMethod($payment['method']);
                // Set new payment in session
                $session->setData('payment_method_in_session', $payment['method']);
            }
        } else {
            // Reset payment
            $this->setPaymentMethod('');
        }

        parent::_construct();
    }

    /**
     * Set payment and remove discounts.
     * @param string $paymentCode
     */
    protected function setPaymentMethod($paymentCode = '')
    {
        $quote = $this->getQuote();
        $quote->getPayment()->setMethod($paymentCode);
        $quote->getBillingAddress()->setPaymentMethod($paymentCode);
        $quote->getShippingAddress()->setPaymentMethod($paymentCode);
        $quote->setTotalsCollectedFlag(false)->collectTotals();

        $cart = Mage::getSingleton('checkout/cart');
        $cart->init();
        $cart->save();
    }
}
