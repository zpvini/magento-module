<?php

/**
 * @author Ruan Azevedo <razevedo@mundipagg.com>
 * @since 2016-06-20
 */
class Uecommerce_Mundipagg_Model_Resource_OfflineRetry extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {    
        //1st argument : modulename/tablename
        //2nd argument : refers to the key field in your database table
        $this->_init('mundipagg/mundipagg_offline_retry', 'id');
    }
}