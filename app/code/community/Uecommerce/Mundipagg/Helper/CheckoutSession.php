<?php

/**
 * Helper methods to checkout session
 */
class Uecommerce_Mundipagg_Helper_CheckoutSession extends Mage_Core_Helper_Abstract
{

    public function getInstance()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get approval_request_success flag from checkout session
     * @return null|string
     */
    public function getApprovalRequest()
    {
        return $this->getInstance()->getApprovalRequestSuccess();
    }

    /**
     * Set approval_request_success flag into checkout session
     * @param string $flag
     */
    public function setApprovalRequest($flag)
    {
        $this->getInstance()->setApprovalRequestSuccess($flag);
    }
}
