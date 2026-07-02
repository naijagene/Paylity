<?php

namespace App\Exceptions;

use Exception;

class VTPassException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'VTPASS_ERROR',
    ) {
        parent::__construct($message);
    }
}
