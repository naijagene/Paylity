<?php

namespace App\Exceptions;

use Exception;

class PaystackException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'PAYSTACK_ERROR',
    ) {
        parent::__construct($message);
    }
}
