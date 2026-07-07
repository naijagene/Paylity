<?php

namespace App\Services\Otp\Providers;

use App\Contracts\Otp\OtpProviderInterface;
use Illuminate\Support\Facades\Log;

class LogOtpProvider implements OtpProviderInterface
{
    public function send(string $phone, string $message): void
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            throw new \RuntimeException('Log OTP provider is only available in local and staging environments.');
        }

        Log::info('OTP delivery (log provider)', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
