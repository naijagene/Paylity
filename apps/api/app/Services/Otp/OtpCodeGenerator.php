<?php

namespace App\Services\Otp;

use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;

class OtpCodeGenerator
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function generate(): string
    {
        $length = max(4, min(8, $this->settings->getInt(SystemSettingKeys::OTP_LENGTH, 6)));
        $max = (10 ** $length) - 1;
        $min = 10 ** ($length - 1);

        return str_pad((string) random_int($min, $max), $length, '0', STR_PAD_LEFT);
    }
}
