<?php

namespace App\Exceptions;

use Exception;

class FraudCheckException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'FRAUD_CHECK_FAILED',
    ) {
        parent::__construct($message);
    }
}
