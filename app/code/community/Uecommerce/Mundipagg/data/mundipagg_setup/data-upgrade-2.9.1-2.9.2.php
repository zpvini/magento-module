<?php

/*
 * Disabled payment methods of more than 1 creditcard.
 * Required because version 2.9.2 implement a new admin validation with this payment methods
 *
 */
Mage::getConfig()->saveConfig('payment/mundipagg_twocreditcards/active', '0');
Mage::getConfig()->saveConfig('payment/mundipagg_threecreditcards/active', '0');
Mage::getConfig()->saveConfig('payment/mundipagg_fourcreditcards/active', '0');
Mage::getConfig()->saveConfig('payment/mundipagg_fivecreditcards/active', '0');
