<?php

class Uecommerce_Mundipagg_Model_Quote_Address_Recurrence extends Mage_Sales_Model_Quote_Address_Total_Abstract {

    public function __construct()
    {
        $this->setCode('mundipagg_recurrence');
    }

    public function changeOrdeValue(Mage_Sales_Model_Quote_Address $address)
    {
        if($address->getQuote()->isVirtual()){
            if ($address->getData('address_type') == 'shipping') return $this;
        }else{
            if ($address->getData('address_type') == 'billing') return $this;
        }

        $this->_setAddress($address);
        $addressObj = $this->_getAddress();
        $totals = $addressObj->$_totals;
        
        parent::collect($address);

        $quote = $address->getQuote();
        $amount = $quote->getMundipaggInterest();
        
        if($amount > 0) {
            $this->_setBaseAmount(0.00);
            $this->_setAmount(0.00);

            $quote->getPayment()->setPaymentInterest($amount);
            $address->setMundipaggInterest($amount);
            
            $this->_setBaseAmount($amount);
            $this->_setAmount($amount);
            
            
            $shippingAmount = $totals['shipping_amount'];
            $baseSubtotal = $totals['base_subtotal'];
            $totalOrderAmount = $baseSubtotal + $shippingAmount + $amount;
            $address->setGrandTotal($totalOrderAmount);
            $address->setBaseGrandTotal($totalOrderAmount);
            $address->save();
        } else {
            $this->_setBaseAmount(0.00);
            $this->_setAmount(0.00);
            
            $quote->getPayment()->setPaymentInterest(0.00);
            $address->setMundipaggInterest(0.00);
        }

		return $this;
	}


    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $msg = Mage::getStoreConfig('payment/mundipagg_recurrencepayment/recurrent_mix_message');
        $paymentMethod = $address->$_totals['payment_method'];
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $totalOrderAmout = $this->getTotalAmout($address->$_totals, $quote->getMundipaggInterest());
        $items = $quote->getAllItems();
        
        //Se o produto for misto
        if ($this->checkRecurrenceMix($items)) {
            //E o método de pagamento for mundipagg_recurrencepayment
            //E estiver sozinho no carrinho
            if (
                $paymentMethod === 'mundipagg_recurrencepayment' &&
                $this->checkItemAlone($items)
            ) {
                //Exibir Parcela do pagamento recorrente por dia, mês, semana, ano
                //No valor, exibir o valor total do pedido dividido pelo número de parcelas
                $address->addTotal(array
                (
                    'code' => $this->getCode(),
                    'title' => $msg,
                    'value' => $this->getRecurrenceValue($items, $totalOrderAmout)
                ));
            } else {
                
            }
         
        //Se for só recorrente
        } else {
            
        }
        
        
        
        
        //Se o produto for misto e o método de pagamento não for recorrência
        //Dar desconto alterando o valor
        //Na alteração, levar em consideração cupons de desconto
        
        
        
        
        //Alterar valor do pedido
        //Na alteração, levar em consideração cupons de desconto
        $this->changeOrdeValue($address);
        
    }
    
    
    private function getRecurrenceValue($items, $orderTotal) 
    {
        foreach ($items as $item) {
            foreach ($item->getOptions() as $option) {
                $product = $option->getProduct();
                $product->load($product->getId());
                if ($product->getMundipaggRecurrences() > 1) {
                    $recurrenValue = $orderTotal / $product->getMundipaggRecurrences();
                    return $recurrenValue;
                }
            }
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
    
    private function getTotalAmout($totals, $interest) {
        return $totals['base_subtotal'] + $totals['shipping_amount'] + $interest;
    }

}
