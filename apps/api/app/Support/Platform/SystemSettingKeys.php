<?php

namespace App\Support\Platform;

final class SystemSettingKeys
{
    public const GUEST_CHECKOUT_ENABLED = 'guest_checkout_enabled';
    public const OTP_ENABLED = 'otp_enabled';
    public const GUEST_LIMIT = 'guest_limit';
    public const OTP_THRESHOLD = 'otp_threshold';
    public const REGISTRATION_THRESHOLD = 'registration_threshold';
    public const MAINTENANCE_MODE = 'maintenance_mode';
    public const ADS_ENABLED = 'ads_enabled';
    public const RECEIPT_VERIFICATION_ENABLED = 'receipt_verification_enabled';
    public const DAILY_PHONE_PRODUCT_LIMIT = 'daily_phone_product_limit';
    public const DAILY_IP_PRODUCT_LIMIT = 'daily_ip_product_limit';
    public const MIN_PRODUCT_AMOUNT = 'min_product_amount';
    public const MAX_PRODUCT_AMOUNT = 'max_product_amount';
    public const OTP_LENGTH = 'otp_length';
    public const OTP_EXPIRY_MINUTES = 'otp_expiry_minutes';
    public const OTP_MAX_ATTEMPTS = 'otp_max_attempts';
    public const OTP_RESEND_COOLDOWN_SECONDS = 'otp_resend_cooldown_seconds';
    public const OTP_PROVIDER = 'otp_provider';
    public const OTP_MESSAGE_TEMPLATE = 'otp_message_template';
}
