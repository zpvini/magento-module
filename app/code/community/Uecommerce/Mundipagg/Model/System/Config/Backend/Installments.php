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

    class Uecommerce_Mundipagg_Model_System_Config_Backend_Installments extends Mage_Core_Model_Config_Data
    {
        /**
         * Process data after load
         */
        protected function _afterLoad()
        {
            $value = $this->getValue();
            $value = Mage::helper('mundipagg/installments')->makeArrayFieldValue($value);
            $this->setValue($value);
        }

        /**
         * Prepare data before save
         */
        protected function _beforeSave()
        {
            $value = $this->getValue();
            $value = Mage::helper('mundipagg/installments')->makeStorableArrayFieldValue($value);
            $this->setValue($value);
        }
    }
