# VTPass Sandbox Test Steps — PAYLITY NG

Manual procedure to certify VTPass sandbox integration before staging soft launch.

---

## Prerequisites

- PAYLITY API running locally or on staging
- VTPass sandbox account credentials
- Paystack sandbox/test keys configured
- Ops console access key configured
- Frontend pointing to API

---

## Step 1 — Configure credentials

Edit `apps/api/.env`:

```env
FEATURE_VTPASS=true
FEATURE_VTPASS_AUTO_FULFILL=false
VTPASS_BASE_URL=https://sandbox.vtpass.com
VTPASS_USERNAME=your_sandbox_username
VTPASS_PASSWORD=your_sandbox_password
VTPASS_API_KEY=your_sandbox_api_key
VTPASS_PUBLIC_KEY=your_sandbox_public_key
VTPASS_TEST_DISCO=IKEDC
VTPASS_TEST_METER_NUMBER=45053854956
VTPASS_TEST_METER_TYPE=prepaid
OPERATOR_ACCESS_KEY=your_ops_key
```

Edit `apps/web/.env.local`:

```env
NEXT_PUBLIC_OPERATOR_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Restart API and web servers after changes.

---

## Step 2 — Run preflight

```bash
cd apps/api
php artisan paylity:preflight
```

**Expected:** No FAIL items. Resolve any FAIL before continuing.

---

## Step 3 — Run credential check

```bash
php artisan paylity:vtpass-check
```

**Expected:**

- `FEATURE_VTPASS` → PASS
- `Credentials` → PASS
- `Reachability` → PASS
- `Merchant verify` → PASS (sandbox test meter accepted)

Record output in the certification report.

---

## Troubleshooting `paylity:vtpass-check`

The command **never crashes**. It always prints a PASS/WARN/FAIL table and exits `1` when any FAIL row is present.

### Configure test meter values

Set these in `apps/api/.env` before running the check:

```env
VTPASS_TEST_DISCO=IKEDC
VTPASS_TEST_METER_NUMBER=45053854956
VTPASS_TEST_METER_TYPE=prepaid
```

If either `VTPASS_TEST_DISCO` or `VTPASS_TEST_METER_NUMBER` is empty, merchant verify is **skipped** with WARN.

### Read FAIL diagnostics safely

Failed merchant verify rows include safe details only:

- `endpoint=merchant-verify`
- `http_status=...`
- `content_type=...`
- `vtpass_message=...` (when VTPass returns a JSON message)
- `safe_body_preview=...` (when no VTPass message is available)

Passwords, API keys, secrets, and auth headers are **never** printed.

### Common failures

| Symptom | Likely cause | Action |
|---------|--------------|--------|
| `VTPass authentication failed` with `http_status=401` | Credentials, auth method, or inactive sandbox account | Confirm sandbox username/password/API key; do not use live credentials on sandbox URL; confirm VTPass sandbox account is approved/active |
| `Non-JSON response received from VTPass` | Wrong base URL, HTML error page, or sandbox outage | Confirm `VTPASS_BASE_URL=https://sandbox.vtpass.com` and inspect `safe_body_preview` |
| `Unable to parse JSON response from VTPass` with `content_type=application/json` | Empty/invalid JSON body from VTPass | Inspect `safe_body_preview`; retry after confirming account status |
| `vtpass_code=016` | Invalid test meter or auth rejected at API layer | Use a valid sandbox meter for the selected disco |
| `VTPASS_TEST_DISCO ... not set` | Test values missing | Set `VTPASS_TEST_DISCO` and `VTPASS_TEST_METER_NUMBER` |
| Reachability FAIL | Network/DNS/firewall | Confirm server can reach `sandbox.vtpass.com` |

**401 troubleshooting notes**

- HTTP `401` means an authentication problem, not necessarily a non-JSON response.
- Sandbox credentials must be used with `https://sandbox.vtpass.com` — live credentials will fail on sandbox.
- Confirm the VTPass sandbox account is approved and active before retrying.
- If `vtpass_message` is missing, read `safe_body_preview` for a sanitized snippet of the response body.

### Re-run after fixes

```bash
php artisan config:clear
php artisan paylity:vtpass-check
```

---

## Step 4 — Complete Paystack payment

1. Open checkout: `http://localhost:3000/checkout?product=airtime`
2. Enter phone and amount (e.g. ₦500 airtime)
3. Initialize transaction and complete Paystack sandbox payment
4. Confirm callback returns to status page with `payment_success`

Repeat for **data** and **electricity** if certifying all products.

For electricity, note meter number and disco used at checkout.

---

## Step 5 — Manual fulfill (ops console)

1. Open `http://localhost:3000/ops`
2. Enter operator access key
3. Search for transaction reference
4. Open transaction detail
5. Click **Manual Fulfill** (only if status is `payment_success` or failed retry)

Alternative via API:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/ops/transactions/PYL-YYYYMMDD-XXXXXX/fulfill \
  -H "X-Operator-Key: YOUR_OPERATOR_ACCESS_KEY"
```

---

## Step 6 — Inspect VTPass response

In ops transaction detail (or database):

```sql
SELECT reference, status, fulfillment_reference, failure_reason, response_payload
FROM transactions
WHERE reference = 'PYL-YYYYMMDD-XXXXXX';
```

Check `response_payload.fulfillment`:

- `code` should be `000` on success
- `requestId` / transaction ID captured in `fulfillment_reference`
- Failure reason readable for support

Review Laravel logs for safe VTPass entries:

```
VTPass request completed. { reference, service, response_code, duration_ms }
```

Confirm no password, API key, or secret appears in logs.

---

## Step 7 — Verify transaction status

1. Customer status page: `/transaction/{reference}`
2. Ops console detail page
3. API: `GET /api/v1/transactions/{reference}`

**Expected success path:** `payment_success` → `fulfillment_pending` → `fulfilled`

---

## Step 8 — Record result

Update:

- `docs/integrations/VTPASS-INTEGRATION-CHECKLIST.md` manual testing log
- `docs/integrations/VTPASS-CERTIFICATION-REPORT.md` certification sections

---

## Optional — Automated sandbox tests

Run only when sandbox credentials are configured (not in CI by default):

```bash
cd apps/api
# Set in .env or inline:
# FEATURE_VTPASS=true
# VTPASS_SANDBOX_TESTS=true

php artisan test --testsuite=Integration
```

---

## Certification checklist

| # | Test | Pass | Fail | Notes |
|---|------|------|------|-------|
| 1 | Preflight passes | ☐ | ☐ | |
| 2 | VTPass check passes | ☐ | ☐ | |
| 3 | Merchant verify (IKEDC test meter) | ☐ | ☐ | |
| 4 | Invalid meter rejected | ☐ | ☐ | |
| 5 | Airtime fulfill → fulfilled | ☐ | ☐ | |
| 6 | Data fulfill → fulfilled | ☐ | ☐ | |
| 7 | Electricity fulfill → fulfilled | ☐ | ☐ | |
| 8 | Failed fulfill shows reason | ☐ | ☐ | |
| 9 | Logs contain no secrets | ☐ | ☐ | |
| 10 | Certification report updated | ☐ | ☐ | |

---

*Document: PAY-015 · VTPass Sandbox Test Steps*
