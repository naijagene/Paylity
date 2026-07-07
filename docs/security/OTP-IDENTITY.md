# PAYLITY OTP & Identity Module

## Purpose

PAYLITY uses a reusable OTP verification service for guest checkout and future identity flows (registration, wallet authorization, password reset, sensitive operations).

## RC1 purchase rules

| Product amount | Guest checkout behavior |
| --- | --- |
| ₦1 – ₦10,000 | Allowed without OTP |
| ₦10,001 – ₦20,000 | OTP required before payment initialization |
| Above ₦20,000 | Blocked until customer accounts are enabled |

These thresholds are enforced by `PurchasePolicyService` using platform settings:

- `otp_threshold` = 10,000
- `guest_limit` = 20,000

## Data model

Table: `otp_verifications`

- Hashed OTP codes only (`code_hash`)
- Purposes: `checkout`, `registration`, `wallet`, `password_reset`, `sensitive_action`
- Status lifecycle: `pending` → `verified` / `expired` / `failed`
- Verification tokens are stored hashed in `metadata` after successful verification

## Service layer

- `OtpService` — request, verify, resend, token validation/consumption
- `OtpCodeGenerator` — configurable length (default 6 digits)
- `OtpProviderInterface`
  - `LogOtpProvider` — local/staging/testing only
  - `SmsOtpProvider` — placeholder for future live SMS integration

## Platform settings

| Key | Default |
| --- | --- |
| `otp_enabled` | true |
| `otp_length` | 6 |
| `otp_expiry_minutes` | 10 |
| `otp_max_attempts` | 5 |
| `otp_resend_cooldown_seconds` | 60 |
| `otp_provider` | log |
| `otp_message_template` | PAYLITY verification template |

## Feature flags

- `otp_verification` — master switch for OTP enforcement
- `sms_provider_live` — enables live SMS provider when configured

## Public API

- `POST /api/v1/otp/request`
- `POST /api/v1/otp/verify`
- `POST /api/v1/otp/resend`

All OTP routes are rate limited separately from checkout.

## Checkout integration

When checkout requires OTP:

1. Frontend shows OTP step between review and payment
2. Customer verifies phone and receives `verification_token`
3. Checkout initialize includes `verification_token`
4. Backend validates token, consumes it, and stores `verified_phone = true` on the transaction

Structured checkout block response:

```json
{
  "success": false,
  "message": "OTP verification is required for this purchase.",
  "errors": {
    "code": "OTP_REQUIRED",
    "otp_required": true,
    "policy": {
      "guest_limit": 20000,
      "otp_threshold": 10000,
      "registration_threshold": 20000
    }
  }
}
```

## Security controls

- OTP codes are hashed at rest
- Plaintext OTP is never stored
- Plaintext OTP is only emitted through `LogOtpProvider` in local/staging/testing
- Brute force protection via attempt counters and lockout
- Dedicated rate limits for request/verify/resend
- Verification tokens are single-use and expire after 30 minutes

## Operations visibility

MVOC shows:

- Transaction detail: OTP required / OTP verified
- Monitoring: OTP enabled status and daily pending/verified/failed counts

## Future reuse

The same OTP service supports upcoming identity flows without changing the core verification model.
