# PAYLITY NG — RC1 Readiness Report

**Release:** 1.0.0-rc1 · **Build:** 2026.07.03-rc1 · **Date:** July 2026

---

## Executive summary

PAYLITY NG Release Candidate 1 (RC1) packages the completed customer checkout experience, Paystack payment flow, VTPass fulfillment foundation, ops console, approved brand UI, and staging deployment documentation into a deployable baseline for **controlled staging validation**.

RC1 is **ready for staging deployment** once infrastructure, secrets, and DNS are provisioned. It is **not ready for unrestricted public live vending**.

---

## RC1 scope summary

| Area | RC1 status |
|------|------------|
| Guest checkout (Airtime, Data, Electricity) | ✅ Complete |
| Provider product catalog + variation validation (PAY-020) | ✅ Complete |
| Customer-facing catalog filtering (PAY-020A) | ✅ Complete |
| Receipt & checkout display polish (PAY-020D) | ✅ Complete |
| Paystack init + verify + webhook | ✅ Complete |
| Transaction engine + status UX | ✅ Complete |
| VTPass fulfillment (manual + auto diagnostics) | ✅ Complete |
| Ops console | ✅ Complete |
| OTP verification for high-value guest checkout (PAY-023) | ✅ Complete |
| Brand theme + official logo | ✅ Complete |
| Pre-launch hardening + rate limits | ✅ Complete |
| Staging deployment docs | ✅ Added in PAY-016 |
| Customer login / wallet / referral | ❌ Out of scope |

---

## What is certified (sandbox)

Per [VTPass Certification Report](../integrations/VTPASS-CERTIFICATION-REPORT.md):

| Product / flow | Sandbox status |
|----------------|----------------|
| Airtime purchase | **Certified** |
| Electricity merchant verify | **Certified** |
| Electricity purchase | **Certified** |
| Data purchase | **Pending** (VTPass code 016) |
| Invalid network rejection | **Certified** |

Paystack test-mode payment initialization and backend verification are implemented and covered by automated tests.

---

## What remains sandbox / not live-ready

| Item | RC1 state |
|------|-----------|
| Paystack | Test keys in templates; live keys not configured |
| VTPass | Sandbox URL and credentials in staging template |
| Auto-fulfillment | **Disabled by default** (`FEATURE_VTPASS_AUTO_FULFILL=false`) |
| Data vending | Not certified — treat as blocked for public launch |
| Customer accounts | Not built |
| Live SMS OTP provider | Not configured (`otp_provider=log`, `sms_provider_live=false`) |
| Wallet / referral | Not built |
| Live electricity meter verify at scale | Sandbox certified; live catalog/credentials TBD |
| WhatsApp support | Requires real `NEXT_PUBLIC_WHATSAPP_URL` on staging |

---

## Deployment requirements

### Infrastructure (hybrid staging — PAY-017)

| Component | Platform | URL |
|-----------|----------|-----|
| Frontend | **Vercel** | `https://staging.paylity.ng` |
| API | **cPanel VPS** | `https://api-staging.paylity.ng` |
| Database | **cPanel VPS** (MySQL/PostgreSQL) | localhost |
| DNS | **cPanel Zone Editor** | CNAME staging → Vercel; A api-staging → VPS |
| SSL | Vercel (frontend) + cPanel AutoSSL (API) | Required |

**Deployment guide:** [HYBRID-STAGING-DEPLOYMENT.md](../deployment/HYBRID-STAGING-DEPLOYMENT.md)

### Configuration

- Backend: see [STAGING-ENV-TEMPLATE.md](../deployment/STAGING-ENV-TEMPLATE.md)
- Frontend: staging public env vars at build time
- Paystack callback + webhook URLs registered
- VTPass sandbox credentials
- Strong `OPERATOR_ACCESS_KEY`

### Verification

```bash
php artisan paylity:preflight   # no FAIL
php artisan migrate --force
php artisan db:seed --class=ProductCatalogSeeder
php artisan paylity:catalog-classify
php artisan paylity:catalog-sync vtpass
php artisan paylity:catalog-classify
php artisan optimize:clear
# Run docs/deployment/STAGING-SMOKE-TESTS.md
```

See [Product Catalog](../integrations/PRODUCT-CATALOG.md) for sync and troubleshooting.

---

## Known risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Data product not VTPass-certified | Failed deliveries for data purchases | Catalog sync + checkout validation; use synced plans only; do not promote until certified |
| Auto-fulfill disabled | Manual ops step required | Expected for RC1; ops runbook + console available |
| Staging uses Paystack test mode | No real money | Acceptable for RC1 staging |
| WhatsApp unset | “Coming Soon” card shown | Configure real URL before soft launch |
| Queue on `sync` | Jobs run inline | Use database/redis queue on staging |

---

## Go / No-Go

### Staging deployment

| Decision | Recommendation |
|----------|----------------|
| **Go** | ✅ **Yes**, after checklist + smoke tests pass |
| Conditions | DNS/SSL live, secrets set, preflight clean, ops key rotated |

### Public launch

| Decision | Recommendation |
|----------|----------------|
| **Go** | ❌ **No-Go** for unrestricted public live vending |
| Blockers | Live Paystack + VTPass credentials, data certification, auto-fulfill decision, ops staffing, monitoring |

---

## Next milestone recommendation

**PAY-017 — Hybrid staging provisioning** (this ticket)

1. Provision DNS in cPanel (CNAME + A records)
2. Deploy API to cPanel following [CPANEL-LARAVEL-API-DEPLOYMENT.md](../deployment/CPANEL-LARAVEL-API-DEPLOYMENT.md)
3. Deploy frontend to Vercel following [VERCEL-FRONTEND-DEPLOYMENT.md](../deployment/VERCEL-FRONTEND-DEPLOYMENT.md)
4. Execute full smoke test matrix and record results
5. Complete VTPass data sandbox certification (resolve code 016) — run catalog sync and test with synced variation codes
6. Decide auto-fulfill policy for soft launch
7. Configure live support channels (email + WhatsApp)
8. Produce soft-launch go/no-go from staging evidence

---

## QA baseline (RC1)

| Suite | Expected |
|-------|----------|
| `php artisan test` | Pass |
| `npm run test` | Pass |
| `npm run lint` | Pass |
| `npm run build` | Pass |
| `php artisan paylity:preflight` | Pass on valid staging config |

---

## Version identity

| Layer | Version | Build |
|-------|---------|-------|
| Backend (`APP_VERSION` / `APP_BUILD`) | 1.0.0-rc1 | 2026.07.03-rc1 |
| Frontend (`NEXT_PUBLIC_*`) | 1.0.0-rc1 | 2026.07.03-rc1 |

Updated in `.env.example` / `.env.local.example` only — local secrets unchanged.

---

## PAY-020D — Receipt & checkout polish

Customer receipts and checkout review now show catalog-enriched product names, masked recipient phone/meter, email when provided, and WAT timestamps on screen and in downloaded HTML receipts.

| Check | Expected |
|-------|----------|
| Airtime receipt | `MTN Airtime` (or network) + masked phone e.g. `0801 XXX 5678` |
| Data receipt | `MTN 1.5GB - 30 Days` style plan name |
| Electricity receipt | `IKEDC Prepaid Electricity` + masked meter |
| Timestamp | `05 Jul 2026, 12:07 AM WAT` on receipt card and download |
| Missing phone | `—` |
| Download vs on-screen | Same product name, phone, timestamp, status fields |
