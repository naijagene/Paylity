<?php

namespace App\Exceptions;

use Exception;

class PaymentVerificationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'PAYMENT_VERIFICATION_FAILED',
    ) {
        parent::__construct($message);
    }
}
