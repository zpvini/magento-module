<?php

class Uecommerce_Mundipagg_Model_Enum_CreditCardTransactionStatusEnum {

	const AUTHORIZED_PENDING_CAPTURE = 'Autorizado e pendente de captura';
	const CAPTURED                   = 'Capturado';
	const PARTIAL_CAPTURE            = 'Capturado parcialmente';
	const NOT_AUTHORIZED             = 'Não autorizado';
	const VOIDED                     = 'Cancelado';
	const PENDING_VOID               = 'Cancelamento pendente';
	const PARTIAL_VOID               = 'Parcialmente cancelado';
	const REFUNDED                   = 'Estornado';
	const PENDING_REFUND             = 'Estorno pendente';
	const PARTIAL_REFUNDED           = 'Parcialmente estornado';
	const WITH_ERROR                 = 'Com erro';
	const NOT_FOUND_ACQUIRER         = 'Não localizado na adquirente';
	const PENDING_AUTHORIZE          = 'Pendente de autorização (Recorrência)';
	const INVALID                    = 'Inválido (Recorrência)';

}