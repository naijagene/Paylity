<?php

namespace App\Contracts\Otp;

interface OtpProviderInterface
{
    public function send(string $phone, string $message): void;
}
