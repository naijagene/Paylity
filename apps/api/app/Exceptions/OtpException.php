<?php

namespace App\Exceptions;

use Exception;

class OtpException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'OTP_ERROR',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
