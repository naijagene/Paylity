<?php

namespace App\Exceptions;

class PaystackConfigurationException extends PaystackException
{
    public function __construct(string $message = 'Paystack secret key is not configured.')
    {
        parent::__construct($message, 'PAYSTACK_NOT_CONFIGURED');
    }
}
