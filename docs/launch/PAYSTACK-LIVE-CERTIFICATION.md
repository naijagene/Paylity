# Paystack Live Certification

PAY-035 adds controlled live payment cutover tooling for PAYLITY. This runbook covers configuration, preflight, certification, and operational safeguards.

## Prerequisites

- Paystack live public and secret keys configured in the API environment
- `PAYSTACK_CALLBACK_URL` pointing to the production customer frontend callback route
- Paystack dashboard webhook configured to `https://<api-host>/api/v1/payments/paystack/webhook`
- Launch mode set to `soft_launch` or `live`
- VTPass live credentials configured for fulfillment
- Recent verified database backup recorded

## Required environment variables

| Variable | Purpose |
| --- | --- |
| `PAYSTACK_PUBLIC_KEY` | Customer checkout public key (`pk_live_…`) |
| `PAYSTACK_SECRET_KEY` | Server-side verification and webhook signing (`sk_live_…`) |
| `PAYSTACK_CALLBACK_URL` | Customer redirect after Paystack checkout |
| `PAYSTACK_BASE_URL` | Usually `https://api.paystack.co` |
| `FEATURE_PAYSTACK` | Must be `true` |
| `FRONTEND_URL` | Production customer frontend origin |
| `CORS_ALLOWED_ORIGINS` | Must include Ops frontend origin |
| `LAUNCH_MODE` | `soft_launch` for controlled rollout |
| `launch_transaction_limit_daily` | Platform setting for daily transaction cap |
| `launch_revenue_limit_daily` | Platform setting for daily gross collection cap |

Never log, export, or expose full Paystack secret keys.

## Step 1 — Validate Paystack mode

```bash
php artisan paylity:paystack-mode
php artisan paylity:paystack-mode --json
```

Expected:

- `detected_mode`: `live`
- `public_key_mode`: `live`
- `secret_key_mode`: `live`
- `verdict`: `valid`

Exit code must be `0`.

## Step 2 — Run live payment preflight

```bash
php artisan paylity:payment-live-preflight
php artisan paylity:payment-live-preflight --strict --json
```

Expected verdicts:

- `READY`
- `READY_WITH_WARNINGS`
- `BLOCKED`

Strict mode must return `BLOCKED` for any unsafe live-payment condition.

## Step 3 — Create certification session

Ops Go-Live Center:

1. Open **Live Payment Certification**
2. Run **Live Payment Preflight**
3. Confirm and create a **Certification Session**

CLI equivalent:

```bash
php artisan paylity:payment-certify-live --product=airtime --amount=100 --json
```

This does not charge a card. It only creates the certification record.

## Step 4 — Complete real-money checkout

Use the normal PAYLITY customer checkout flow:

- Product: airtime
- Amount: ₦100
- No voucher

Record the resulting PAYLITY transaction reference.

## Step 5 — Link and refresh certification

Ops:

1. Enter the transaction reference
2. Click **Link Transaction Reference**
3. Click **Refresh Certification**

CLI:

```bash
php artisan paylity:payment-certify-live --reference=PYL-YYYYMMDD-XXXXXX --json
```

## Step 6 — Finalize certification

Only finalize after evidence shows:

- Payment verified
- Fulfillment succeeded once
- Ledger postings balanced
- Receipt available
- No critical finance alerts

Ops: confirm **Finalize Certification**

CLI:

```bash
php artisan paylity:payment-certify-live --reference=PYL-YYYYMMDD-XXXXXX --finalize --json
```

Final verdicts:

- `CERTIFIED`
- `CERTIFIED_WITH_WARNINGS`
- `FAILED`
- `INCOMPLETE`

PAYLITY is not production certified until a real Paystack LIVE transaction completes and certification evidence is verified.

## Paystack dashboard configuration

- Callback URL: value of `PAYSTACK_CALLBACK_URL`
- Webhook URL: `https://<api-host>/api/v1/payments/paystack/webhook`
- Live keys only in production launch mode

## Deployment

API VPS:

```bash
php artisan migrate --force
php artisan db:seed --class=PlatformSettingsSeeder --force
php artisan paylity:paystack-mode
php artisan paylity:payment-live-preflight --strict
```

Ops / Web (Vercel):

```bash
npm run build
```

## Audit trail

Production-sensitive actions are recorded in `launch_audit_events`:

- Live preflight run
- Certification session created
- Transaction linked
- Certification finalized
- Launch mode changed
- Maintenance entered
- Soft launch restored
- Live rollback
