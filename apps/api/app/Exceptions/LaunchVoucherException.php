<?php

namespace App\Exceptions;

use RuntimeException;

class LaunchVoucherException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'VOUCHER_INVALID',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
