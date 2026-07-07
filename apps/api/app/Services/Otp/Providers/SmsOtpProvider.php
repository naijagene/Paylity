<?php

namespace App\Services\Otp\Providers;

use App\Contracts\Otp\OtpProviderInterface;

class SmsOtpProvider implements OtpProviderInterface
{
    public function send(string $phone, string $message): void
    {
        throw new \RuntimeException('Live SMS OTP provider is not configured yet.');
    }
}
