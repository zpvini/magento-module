<?php

class Uecommerce_Mundipagg_Model_Enum_CreditCardTransactionStatusEnum
{

    const AUTHORIZED_PENDING_CAPTURE = 'AuthorizedPendingCapture';
    const CAPTURED                   = 'Captured';
    const PARTIAL_CAPTURE            = 'PartialCapture';
    const NOT_AUTHORIZED             = 'NotAuthorized';
    const VOIDED                     = 'Voided';
    const PENDING_VOID               = 'PendingVoid';
    const PARTIAL_VOID               = 'PartialVoid';
    const REFUNDED                   = 'Refunded';
    const PENDING_REFUND             = 'PendingRefund';
    const PARTIAL_REFUNDED           = 'PartialRefunded';
    const WITH_ERROR                 = 'WithError';
    const NOT_FOUND_ACQUIRER         = 'NotFoundInAcquirer';
    const PENDING_AUTHORIZE          = 'PendingAuthorize';
    const INVALID                    = 'Invalid';
}
