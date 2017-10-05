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

class Uecommerce_Mundipagg_Block_Standard_Debit extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('mundipagg/debit.phtml');
    }

    /**
     * Debit Types
     */
    public function getDebitTypes()
    {
        $debitTypes = Mage::getStoreConfig('payment/mundipagg_debit/debit_types');
        
        if ($debitTypes != '') {
            $debitTypes = explode(",", $debitTypes);
        } else {
            $debitTypes = array();
        }
        
        return $debitTypes;
    }
}
