# Staging Smoke Tests — PAYLITY NG RC1

Run after each staging deployment on the **hybrid stack** (Vercel frontend + cPanel API). Record pass/fail, tester, date, and notes.

**Environment:** Staging · **Version:** 1.0.0-rc1 · **Build:** 2026.07.03-rc1

**URLs**

| Service | URL |
|---------|-----|
| Frontend (Vercel) | `https://staging.paylity.ng` |
| API (cPanel) | `https://api-staging.paylity.ng` |

**Deployment guide:** [HYBRID-STAGING-DEPLOYMENT.md](./HYBRID-STAGING-DEPLOYMENT.md)

---

## Prerequisites

- [ ] cPanel API `.env` matches [STAGING-ENV-TEMPLATE.md](./STAGING-ENV-TEMPLATE.md)
- [ ] Vercel env vars match [STAGING-ENV-TEMPLATE.md](./STAGING-ENV-TEMPLATE.md) and deployment rebuilt
- [ ] DNS: `staging` CNAME → Vercel, `api-staging` A → VPS
- [ ] SSL valid on both domains
- [ ] `php artisan paylity:preflight` — no FAIL items (run on cPanel via SSH)
- [ ] `php artisan db:seed --class=ProductCatalogSeeder` (first deploy / after catalog migration)
- [ ] `php artisan paylity:catalog-sync vtpass` — data variations synced (see [PRODUCT-CATALOG.md](../integrations/PRODUCT-CATALOG.md))
- [ ] Queue worker running on cPanel (if async jobs used)

---

## 1. Health check

```bash
curl -s https://api-staging.paylity.ng/api/v1/health | jq
```

**Expected**

- HTTP 200
- `success: true`
- `data.version`: `1.0.0-rc1`
- `data.build`: `2026.07.03-rc1`
- `data.environment`: `staging`

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 2. Product catalog

```bash
curl -s "https://api-staging.paylity.ng/api/v1/catalog/products?category=data" | jq
```

**Expected**

- HTTP 200, `success: true`
- `data.provider`: `vtpass`
- `data.data_services[]` includes MTN/Airtel/Glo/9mobile with `variations[]`
- Each variation has `variation_code`, `name`, `amount`, `fixed_price`

**Checkout UI**

1. Open `/checkout?product=data`
2. Select network — plans load from API (not hardcoded IDs)
3. If catalog API fails, payment initialize is blocked

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 3. Homepage & branding

Open `https://staging.paylity.ng`

- [ ] Official PNG logo in header and footer
- [ ] Hero: “Fast **Utility** Payments”
- [ ] Service cards link to checkout
- [ ] Trust strip visible
- [ ] Support section shows Email + WhatsApp cards
- [ ] Footer shows `Version 1.0.0-rc1`
- [ ] Page title: “PAYLITY NG — Fast Utility Payments”
- [ ] Favicon loads

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 4. Checkout initialize

1. Go to `/checkout?product=airtime`
2. Enter valid phone + amount ≤ ₦10,000
3. Continue to review and initialize payment

**Data checkout (PAY-020)**

1. Go to `/checkout?product=data`
2. Select network and a plan from catalog API
3. Initialize payment — invalid/stale variation must be rejected before Paystack

**Expected**

- API returns transaction reference `PYL-YYYYMMDD-XXXXXX`
- Paystack authorization URL returned (when `FEATURE_PAYSTACK=true`)

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 5. Paystack test payment

Use Paystack test card (test mode):

- Card: `4084084084084081`
- Expiry: any future date
- CVV: `408`
- OTP: `123456`

**Expected**

- Redirect to `/payment/callback`
- Payment verified as success or pending then success

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 6. Callback verification

After test payment:

- [ ] `/payment/callback?reference=PYL-...` shows success UI
- [ ] Transaction reference displayed
- [ ] Support card includes reference in email/WhatsApp links

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 7. Transaction status page

Open `/transaction/{reference}`

- [ ] Payment badge correct
- [ ] Fulfillment badge reflects delivery state
- [ ] Polling UX works while awaiting delivery
- [ ] Receipt card renders
- [ ] Electricity token card appears when fulfilled (electricity only)

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 8. Ops console login

1. Open `/ops`
2. Enter `OPERATOR_ACCESS_KEY`

**Expected**

- Dashboard loads transaction list
- Detail page accessible

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 9. Manual fulfillment (default staging mode)

With `FEATURE_VTPASS_AUTO_FULFILL=false`:

1. Complete a test payment
2. In ops detail, run **Manual Fulfill**
3. Refresh transaction status

**Expected**

- Status moves to fulfilled (or explicit failure with reason)
- Ops diagnostics visible on failure

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 10. Auto-fulfillment test (optional)

⚠️ Enable only for intentional test window:

```env
FEATURE_VTPASS_AUTO_FULFILL=true
```

1. Complete new test payment
2. Confirm auto-fulfill attempt in transaction/API diagnostics
3. Re-disable flag after test

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail ☐ Skipped | |

---

## 11. VTPass sandbox product checks

Run from API host (engineering):

```bash
# Connectivity / merchant verify (when command available)
php artisan paylity:vtpass-check

# Integration tests (sandbox credentials required)
VTPASS_SANDBOX_TESTS=true php artisan test --filter=VTPass
```

| Product | Expected (sandbox) | Result |
|---------|-------------------|--------|
| Airtime | Purchase succeeds | ☐ |
| Data | May fail with VTPass code 016 — document if so | ☐ |
| Electricity verify | Merchant verify succeeds | ☐ |
| Electricity purchase | Token/units returned | ☐ |

---

## 12. Rate limit check

Rapidly submit checkout initialize (>10 requests/min from same IP):

**Expected**

- HTTP 429
- JSON envelope with `RATE_LIMIT_EXCEEDED`

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 13. Legal & static pages

- [ ] `/privacy` loads
- [ ] `/terms` loads
- [ ] Footer links work

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## 14. Mobile responsive check

On phone or narrow viewport:

- [ ] Homepage readable, cards stack cleanly
- [ ] Checkout usable
- [ ] Support cards stack without overlap
- [ ] Logo scales (~36px mobile height)

| Result | Notes |
|--------|-------|
| ☐ Pass ☐ Fail | |

---

## Summary

| Area | Pass | Fail | Skipped |
|------|------|------|---------|
| Total | | | |

**Tester:** _______________  
**Date:** _______________  
**Recommendation:** ☐ Staging Go ☐ Staging No-Go
