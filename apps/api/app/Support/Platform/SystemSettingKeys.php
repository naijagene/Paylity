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
    public const INCIDENT_MODE = 'incident_mode';
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
    public const VTPASS_LIVE_SAFETY_MODE = 'vtpass_live_safety_mode';
    public const VTPASS_LIVE_TEST_MAX_AMOUNT = 'vtpass_live_test_max_amount';
    public const FULFILLMENT_RETRY_MAX_ATTEMPTS = 'fulfillment_retry_max_attempts';
    public const FULFILLMENT_RETRY_INTERVALS_MINUTES = 'fulfillment_retry_intervals_minutes';
    public const PAYMENT_RECONCILE_STALE_MINUTES = 'payment_reconcile_stale_minutes';
    public const FULFILLMENT_STALE_MINUTES = 'fulfillment_stale_minutes';
    public const PAYMENT_PENDING_STALE_MINUTES = 'payment_pending_stale_minutes';
    public const FULFILLMENT_PROCESSING_STALE_MINUTES = 'fulfillment_processing_stale_minutes';
    public const FULFILLMENT_UNCERTAIN_ESCALATION_MINUTES = 'fulfillment_uncertain_escalation_minutes';
    public const RECONCILIATION_BATCH_SIZE = 'reconciliation_batch_size';
    public const RECONCILIATION_MAX_AGE_DAYS = 'reconciliation_max_age_days';
    public const WALLET_LOW_BALANCE_THRESHOLD = 'wallet_low_balance_threshold';
    public const WALLET_CRITICAL_BALANCE_THRESHOLD = 'wallet_critical_balance_threshold';
    public const WALLET_REFRESH_SECONDS = 'wallet_refresh_seconds';
    public const FINANCIAL_SETTLEMENT_DIFFERENCE_THRESHOLD = 'financial_settlement_difference_threshold';
    public const FINANCIAL_NEGATIVE_MARGIN_ALERT_ENABLED = 'financial_negative_margin_alert_enabled';
    public const FINANCIAL_CLOSE_HOUR = 'financial_close_hour';
    public const FINANCIAL_BACKFILL_BATCH_SIZE = 'financial_backfill_batch_size';
    public const FINANCIAL_CLEARING_STALE_HOURS = 'financial_clearing_stale_hours';
    public const FINANCIAL_PAYSTACK_FEE_BASIS_POINTS = 'financial_paystack_fee_basis_points';
    public const FINANCIAL_PAYSTACK_FEE_FLAT_KOBO = 'financial_paystack_fee_flat_kobo';

    public const LAUNCH_MODE = 'launch_mode';
    public const LAUNCH_STARTED_AT = 'launch_started_at';
    public const LAUNCH_TRANSACTION_LIMIT_DAILY = 'launch_transaction_limit_daily';
    public const LAUNCH_REVENUE_LIMIT_DAILY = 'launch_revenue_limit_daily';
    public const LAUNCH_ALLOWED_PRODUCTS = 'launch_allowed_products';
    public const LAUNCH_SUPPORT_PHONE = 'launch_support_phone';
    public const LAUNCH_SUPPORT_EMAIL = 'launch_support_email';
    public const LAUNCH_INCIDENT_MESSAGE = 'launch_incident_message';
    public const SCHEDULER_LAST_RUN_AT = 'scheduler_last_run_at';
    public const PREFLIGHT_LAST_RUN_AT = 'preflight_last_run_at';
    public const PREFLIGHT_LAST_STATUS = 'preflight_last_status';
    public const BACKUP_LAST_RUN_AT = 'backup_last_run_at';
    public const BACKUP_LAST_CHECKSUM = 'backup_last_checksum';
    public const BACKUP_LAST_PATH = 'backup_last_path';
    public const BACKUP_LAST_VERIFIED_AT = 'backup_last_verified_at';
}
