<?php

class Uecommerce_Mundipagg_Model_Providervalidation extends Mage_Core_Model_Config_Data {

	public function save() {
		$antifraudProvider = $this->getValue();

		if($antifraudProvider == Uecommerce_Mundipagg_Model_Source_Antifraud::ANTIFRAUD_NONE){
			Mage::throwException("Erro ao tentar salvar: fornecedor de anti-fraude não informado.");
		}

		return parent::save();
	}

}