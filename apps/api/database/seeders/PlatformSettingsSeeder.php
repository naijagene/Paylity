<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Models\SystemSetting;
use App\Support\Platform\FeatureFlagKeys;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Database\Seeder;

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => SystemSettingKeys::GUEST_CHECKOUT_ENABLED,
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Allow guest checkout without a customer account.',
            ],
            [
                'key' => SystemSettingKeys::OTP_ENABLED,
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Require phone OTP verification above the OTP threshold.',
            ],
            [
                'key' => SystemSettingKeys::GUEST_LIMIT,
                'value' => '20000',
                'type' => 'integer',
                'description' => 'Maximum product amount for unverified guest checkout.',
            ],
            [
                'key' => SystemSettingKeys::OTP_THRESHOLD,
                'value' => '10000',
                'type' => 'integer',
                'description' => 'Product amounts above this value require OTP verification.',
            ],
            [
                'key' => SystemSettingKeys::REGISTRATION_THRESHOLD,
                'value' => '20000',
                'type' => 'integer',
                'description' => 'Product amounts above this value require a registered customer account.',
            ],
            [
                'key' => SystemSettingKeys::MAINTENANCE_MODE,
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Temporarily disable checkout while maintenance is in progress.',
            ],
            [
                'key' => SystemSettingKeys::ADS_ENABLED,
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Show promotional ad slots in customer-facing flows.',
            ],
            [
                'key' => SystemSettingKeys::RECEIPT_VERIFICATION_ENABLED,
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable public receipt verification pages and QR codes.',
            ],
            [
                'key' => SystemSettingKeys::DAILY_PHONE_PRODUCT_LIMIT,
                'value' => '20000',
                'type' => 'integer',
                'description' => 'Rolling 24-hour product amount limit per phone number.',
            ],
            [
                'key' => SystemSettingKeys::DAILY_IP_PRODUCT_LIMIT,
                'value' => '30000',
                'type' => 'integer',
                'description' => 'Rolling 24-hour product amount limit per IP address.',
            ],
            [
                'key' => SystemSettingKeys::MIN_PRODUCT_AMOUNT,
                'value' => '50',
                'type' => 'integer',
                'description' => 'Minimum allowed checkout product amount.',
            ],
            [
                'key' => SystemSettingKeys::MAX_PRODUCT_AMOUNT,
                'value' => '1000000',
                'type' => 'integer',
                'description' => 'Maximum allowed checkout product amount.',
            ],
            [
                'key' => SystemSettingKeys::OTP_LENGTH,
                'value' => '6',
                'type' => 'integer',
                'description' => 'Number of digits in OTP codes.',
            ],
            [
                'key' => SystemSettingKeys::OTP_EXPIRY_MINUTES,
                'value' => '10',
                'type' => 'integer',
                'description' => 'Minutes before an OTP expires.',
            ],
            [
                'key' => SystemSettingKeys::OTP_MAX_ATTEMPTS,
                'value' => '5',
                'type' => 'integer',
                'description' => 'Maximum OTP verification attempts per request.',
            ],
            [
                'key' => SystemSettingKeys::OTP_RESEND_COOLDOWN_SECONDS,
                'value' => '60',
                'type' => 'integer',
                'description' => 'Minimum seconds before an OTP can be resent.',
            ],
            [
                'key' => SystemSettingKeys::OTP_PROVIDER,
                'value' => 'log',
                'type' => 'string',
                'description' => 'OTP delivery provider key (log or sms).',
            ],
            [
                'key' => SystemSettingKeys::OTP_MESSAGE_TEMPLATE,
                'value' => 'Your PAYLITY verification code is :code. It expires in :minutes minutes.',
                'type' => 'string',
                'description' => 'SMS/log message template for OTP delivery.',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'description' => $setting['description'],
                ],
            );
        }

        $flags = [
            [
                'key' => FeatureFlagKeys::CUSTOMER_ACCOUNTS,
                'enabled' => false,
                'description' => 'Customer account registration and login.',
            ],
            [
                'key' => FeatureFlagKeys::WALLET,
                'enabled' => false,
                'description' => 'PAYLITY wallet balances and top-ups.',
            ],
            [
                'key' => FeatureFlagKeys::REFERRAL,
                'enabled' => false,
                'description' => 'Referral rewards program.',
            ],
            [
                'key' => FeatureFlagKeys::LOYALTY,
                'enabled' => false,
                'description' => 'Loyalty points and rewards.',
            ],
            [
                'key' => FeatureFlagKeys::SAVED_BENEFICIARIES,
                'enabled' => false,
                'description' => 'Saved recipients and beneficiaries.',
            ],
            [
                'key' => FeatureFlagKeys::VIRTUAL_ACCOUNTS,
                'enabled' => false,
                'description' => 'Dedicated virtual account numbers.',
            ],
            [
                'key' => FeatureFlagKeys::PAYSTACK,
                'enabled' => false,
                'description' => 'Paystack payment collection.',
            ],
            [
                'key' => FeatureFlagKeys::VTPASS,
                'enabled' => false,
                'description' => 'VTPass fulfillment provider.',
            ],
            [
                'key' => FeatureFlagKeys::VTPASS_AUTO_FULFILL,
                'enabled' => false,
                'description' => 'Automatically fulfill transactions after successful payment.',
            ],
            [
                'key' => FeatureFlagKeys::OTP_VERIFICATION,
                'enabled' => true,
                'description' => 'Phone OTP verification for high-value guest checkout.',
            ],
            [
                'key' => FeatureFlagKeys::SMS_PROVIDER_LIVE,
                'enabled' => false,
                'description' => 'Use the configured live SMS provider for OTP delivery.',
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::query()->updateOrCreate(
                ['key' => $flag['key']],
                [
                    'enabled' => $flag['enabled'],
                    'description' => $flag['description'],
                ],
            );
        }
    }
}
