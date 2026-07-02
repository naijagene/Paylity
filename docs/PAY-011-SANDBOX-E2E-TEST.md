# PAY-011 — Sandbox End-to-End Transaction Test

This guide proves PAYLITY’s full sandbox transaction flow:

**Checkout → Paystack test payment → Backend verification → Manual VTPass fulfillment → Status check → Receipt display**

Use this before any production launch. All steps assume **sandbox/test credentials only**.

---

## Required environment variables

### Backend (`apps/api/.env`)

```env
APP_URL=http://127.0.0.1:8000

# Database (local default)
DB_CONNECTION=sqlite

# Paystack (test keys from Paystack dashboard)
PAYSTACK_PUBLIC_KEY=pk_test_...
PAYSTACK_SECRET_KEY=sk_test_...
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_CALLBACK_URL=http://localhost:3000/payment/callback
FEATURE_PAYSTACK=true

# VTPass (sandbox credentials from VTPass dashboard)
VTPASS_BASE_URL=https://sandbox.vtpass.com
VTPASS_USERNAME=your_sandbox_username
VTPASS_PASSWORD=your_sandbox_password
VTPASS_API_KEY=your_sandbox_api_key
VTPASS_PUBLIC_KEY=
VTPASS_SECRET_KEY=
FEATURE_VTPASS=true
FEATURE_VTPASS_AUTO_FULFILL=false
```

### Frontend (`apps/web/.env.local`)

```env
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

### Safety defaults (must stay off for sandbox validation)

| Variable | Required value | Why |
|----------|----------------|-----|
| `FEATURE_VTPASS_AUTO_FULFILL` | `false` | Manual fulfillment proves each stage independently |
| `VTPASS_BASE_URL` | `https://sandbox.vtpass.com` | Never use live URL in sandbox testing |
| `FEATURE_VTPASS` | `true` only when testing fulfillment | Can stay `false` until Stage 5 |

---

## Backend startup

```bash
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Verify health:

```bash
curl http://127.0.0.1:8000/api/v1/health
```

Expected: `{ "success": true, ... }`

---

## Frontend startup

```bash
cd apps/web
npm install
cp .env.local.example .env.local
npm run dev
```

Open: [http://localhost:3000](http://localhost:3000)

---

## End-to-end flow

### Stage 1 — Checkout initialization

1. Open [http://localhost:3000/checkout?product=airtime](http://localhost:3000/checkout?product=airtime)
2. Enter phone number and select amount (e.g. ₦1,000)
3. Continue to Review → **Initialize Transaction**

**Expected API status:** `payment_pending`

**Expected behavior:** Browser redirects to Paystack checkout (`authorization_url`).

| Status | Meaning |
|--------|---------|
| `created` | Transaction created, Paystack not initialized |
| `payment_pending` | Paystack initialized, awaiting customer payment |

---

### Stage 2 — Paystack test payment

On Paystack checkout page, use Paystack test card:

| Field | Value |
|-------|-------|
| Card number | `4084084084084081` |
| Expiry | Any future date |
| CVV | `408` |
| PIN | `0000` |
| OTP | `123456` |

After payment, Paystack redirects to:

```
http://localhost:3000/payment/callback?reference=PYL-YYYYMMDD-XXXXXX
```

**Do not treat callback alone as success.** The frontend calls Laravel verify.

---

### Stage 3 — Backend payment verification

The callback page automatically calls:

```
GET /api/v1/payments/paystack/verify/{reference}
```

Laravel verifies with Paystack `GET /transaction/verify/{reference}` before updating status.

**Expected UI:** “Payment Successful” with **Fulfillment: Awaiting delivery**

**Expected API status:** `payment_success`

**Expected fulfillment_status:** `awaiting_delivery`

Manual verify (optional):

```bash
curl http://127.0.0.1:8000/api/v1/payments/paystack/verify/PYL-YYYYMMDD-XXXXXX
```

| Status | Meaning |
|--------|---------|
| `payment_success` | Paystack confirmed payment |
| `payment_failed` | Paystack reported failure |
| `payment_pending` | Payment still processing |

---

### Stage 4 — View transaction status

From the callback success page, click **View Transaction Status**.

Or open directly:

```
http://localhost:3000/transaction/PYL-YYYYMMDD-XXXXXX
```

**Expected display:**

- Reference, product, phone
- Product amount, convenience fee, gateway fee, total paid
- Payment status: **Payment successful**
- Fulfillment status: **Awaiting delivery**

API check:

```bash
curl http://127.0.0.1:8000/api/v1/transactions/PYL-YYYYMMDD-XXXXXX
```

Response must include:

- `payment_provider`, `payment_reference`
- `fulfillment_provider` (null until fulfilled)
- `fulfillment_reference` (null until fulfilled)
- `status`, `failure_reason`, timestamps

---

### Stage 5 — Manual VTPass sandbox fulfillment

**Prerequisites:**

- Transaction status is `payment_success`
- `FEATURE_VTPASS=true`
- `FEATURE_VTPASS_AUTO_FULFILL=false` (manual only)
- VTPass sandbox credentials configured

Trigger fulfillment:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/transactions/PYL-YYYYMMDD-XXXXXX/fulfill
```

**Expected progression:**

| Step | Status |
|------|--------|
| Before call | `payment_success` |
| During VTPass call | `fulfillment_pending` |
| VTPass success | `fulfilled` |
| VTPass failure | `failed` (with `failure_reason`) |

**Expected success response:**

```json
{
  "success": true,
  "data": {
    "status": "fulfilled",
    "fulfillment_provider": "vtpass",
    "fulfillment_reference": "...",
    "fulfillment_status": "fulfilled"
  }
}
```

Refresh transaction status page — fulfillment should show **Delivered**.

---

## Expected statuses at each stage

| Stage | Transaction status | Fulfillment display |
|-------|-------------------|---------------------|
| After checkout init | `payment_pending` | Not started |
| After Paystack verify | `payment_success` | Awaiting delivery |
| During VTPass fulfill | `fulfillment_pending` | Delivery in progress |
| VTPass success | `fulfilled` | Delivered |
| VTPass failure | `failed` | Delivery failed |

---

## Common failure points

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| CORS error in browser | API CORS not allowing localhost:3000 | Check `apps/api/config/cors.php`, restart API |
| Paystack init fails | Missing/invalid `PAYSTACK_SECRET_KEY` | Add test secret key, set `FEATURE_PAYSTACK=true` |
| Callback shows “Verification unavailable” | API not running | Start `php artisan serve` |
| Verify returns amount mismatch | Paystack amount ≠ payable total | Check fee calculation; amount sent in kobo |
| Fulfillment returns 503 disabled | `FEATURE_VTPASS=false` | Set `FEATURE_VTPASS=true` with credentials |
| Fulfillment returns 422 invalid status | Payment not verified yet | Complete Stage 3 first |
| VTPass returns failed | Wrong sandbox service ID or credentials | Check adapter mapping and VTPass sandbox docs |
| Transaction page 404 | Wrong reference or DB reset | Use reference from current session |

---

## Recovery steps

### Payment stuck at `payment_pending`

1. Open callback URL with reference, or call verify endpoint manually
2. Check Paystack dashboard for transaction status
3. Use **Check Again** on callback page if pending

### Payment verified but fulfillment not started

1. Confirm `FEATURE_VTPASS_AUTO_FULFILL=false` (expected for sandbox)
2. Run manual fulfill POST (Stage 5)
3. Check `failure_reason` on transaction if status is `failed`

### Need to restart test

1. Start a new checkout (new reference generated each time)
2. Do not reuse references after DB migration reset

---

## Launch readiness checklist

Before moving beyond sandbox:

- [ ] Full E2E flow completed with Paystack **test** keys
- [ ] VTPass fulfillment tested on **sandbox.vtpass.com** only
- [ ] `FEATURE_VTPASS_AUTO_FULFILL=false` validated (manual fulfill works)
- [ ] Transaction status page shows correct payment + fulfillment state
- [ ] Callback page links to transaction status page
- [ ] `php artisan test` passes
- [ ] `npm run lint` and `npm run build` pass
- [ ] No live Paystack/VTPass credentials in `.env`
- [ ] Recovery steps documented and understood by team

---

## Quick command reference

```bash
# Health
curl http://127.0.0.1:8000/api/v1/health

# Verify payment
curl http://127.0.0.1:8000/api/v1/payments/paystack/verify/PYL-YYYYMMDD-XXXXXX

# Get transaction
curl http://127.0.0.1:8000/api/v1/transactions/PYL-YYYYMMDD-XXXXXX

# Manual fulfill (sandbox only)
curl -X POST http://127.0.0.1:8000/api/v1/transactions/PYL-YYYYMMDD-XXXXXX/fulfill

# Run tests
cd apps/api && php artisan test
cd apps/web && npm run lint && npm run build
```

---

## Internal endpoints (not exposed in UI)

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/v1/transactions/{reference}/fulfill` | Manual VTPass fulfillment (internal testing) |
| GET | `/api/v1/payments/paystack/verify/{reference}` | Backend Paystack verification |
| POST | `/api/v1/payments/paystack/webhook` | Paystack webhook (signature validated) |

**Manual fulfill rules:**

- Only call after `payment_success`
- Requires `FEATURE_VTPASS=true`
- Keep `FEATURE_VTPASS_AUTO_FULFILL=false` during sandbox validation
- Never expose fulfill button in public frontend for v1
