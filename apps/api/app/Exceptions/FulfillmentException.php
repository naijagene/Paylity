<?php

namespace App\Exceptions;

use Exception;

class FulfillmentException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'FULFILLMENT_ERROR',
    ) {
        parent::__construct($message);
    }
}
