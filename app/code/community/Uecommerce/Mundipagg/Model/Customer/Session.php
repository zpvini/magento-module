<?php

class Uecommerce_Mundipagg_Model_Customer_Session
{

    const SESSION_ID = 'session_id';

    public static function getSessionId()
    {
        $session = Mage::getSingleton('customer/session');
        return $session->getData(self::SESSION_ID);
    }

    public static function setSessionId($sessionId)
    {
        $session = Mage::getSingleton('customer/session');
        $session->setData(self::SESSION_ID, $sessionId);
    }
}
