# Staging Deployment Checklist — PAYLITY NG RC1

Use this checklist when deploying **Release Candidate 1** to staging on the **hybrid stack**:

| Component | Platform | URL |
|-----------|----------|-----|
| Frontend | **Vercel** | `https://staging.paylity.ng` |
| API | **cPanel VPS** | `https://api-staging.paylity.ng` |
| Database | **cPanel VPS** | MySQL or PostgreSQL |

**Primary guide:** [HYBRID-STAGING-DEPLOYMENT.md](./HYBRID-STAGING-DEPLOYMENT.md)

---

## 1. DNS (cPanel Zone Editor)

- [ ] **CNAME** `staging` → Vercel target (e.g. `cname.vercel-dns.com`)
- [ ] **A** record `api-staging` → VPS public IP
- [ ] DNS propagated (check with `dig` or online DNS tool)
- [ ] TTL appropriate for rollback window

Reference: [HYBRID-STAGING-DEPLOYMENT.md § DNS](./HYBRID-STAGING-DEPLOYMENT.md#1-dns-records-cpanel-zone-editor)

---

## 2. SSL / TLS

- [ ] `staging.paylity.ng` — SSL issued by **Vercel** (after CNAME validates)
- [ ] `api-staging.paylity.ng` — SSL issued by **cPanel** AutoSSL / Let’s Encrypt
- [ ] HTTPS loads on both URLs without certificate warnings

---

## 3. cPanel — Laravel API

Full guide: [CPANEL-LARAVEL-API-DEPLOYMENT.md](./CPANEL-LARAVEL-API-DEPLOYMENT.md)

- [ ] Subdomain `api-staging.paylity.ng` created
- [ ] Document root → `.../apps/api/public`
- [ ] PHP 8.2+ with required extensions
- [ ] Code deployed (`apps/api` via Git/SFTP)
- [ ] `.env` from [STAGING-ENV-TEMPLATE.md](./STAGING-ENV-TEMPLATE.md)
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan key:generate` (first deploy only)
- [ ] `php artisan migrate --force`
- [ ] `php artisan optimize`
- [ ] `php artisan paylity:preflight` — no FAIL items
- [ ] `storage/` and `bootstrap/cache/` writable
- [ ] Health check: `curl https://api-staging.paylity.ng/api/v1/health`

---

## 4. cPanel — Database

- [ ] MySQL or PostgreSQL database created
- [ ] Database user created with privileges
- [ ] Credentials in API `.env` (use cPanel-prefixed names)
- [ ] Migrations applied successfully
- [ ] Backup schedule enabled in cPanel

---

## 5. cPanel — Cron & queue

- [ ] Scheduler cron: `* * * * * php .../artisan schedule:run`
- [ ] Queue worker cron or long-running process configured
- [ ] `QUEUE_CONNECTION=database` (not `sync`)
- [ ] Failed jobs monitored in `failed_jobs` table

Reference: [CPANEL-LARAVEL-API-DEPLOYMENT.md § Queue](./CPANEL-LARAVEL-API-DEPLOYMENT.md#10-queue-worker-cpanel-options)

---

## 6. Vercel — Next.js frontend

Full guide: [VERCEL-FRONTEND-DEPLOYMENT.md](./VERCEL-FRONTEND-DEPLOYMENT.md)

- [ ] GitHub repo imported
- [ ] Root directory: `apps/web`
- [ ] Build command: `npm run build`
- [ ] All `NEXT_PUBLIC_*` vars set from [STAGING-ENV-TEMPLATE.md](./STAGING-ENV-TEMPLATE.md)
- [ ] Custom domain `staging.paylity.ng` added and verified
- [ ] Production deployment successful
- [ ] Homepage and favicon load

---

## 7. Environment configuration

- [ ] `APP_ENV=staging` (API)
- [ ] `APP_DEBUG=false` (API)
- [ ] `APP_VERSION=1.0.0-rc1` / `APP_BUILD=2026.07.03-rc1`
- [ ] `FRONTEND_URL=https://staging.paylity.ng`
- [ ] `APP_URL=https://api-staging.paylity.ng`
- [ ] `OPERATOR_ACCESS_KEY` set and stored securely
- [ ] `FEATURE_VTPASS_AUTO_FULFILL=false` unless testing auto-delivery
- [ ] `NEXT_PUBLIC_ENVIRONMENT=Staging` (Vercel)

---

## 8. Paystack (test mode)

- [ ] Test public/secret keys in API `.env`
- [ ] Callback URL → `https://staging.paylity.ng/payment/callback`
- [ ] Webhook URL → `https://api-staging.paylity.ng/api/v1/payments/paystack/webhook`
- [ ] Test payment completes end-to-end

---

## 9. VTPass (sandbox)

- [ ] `VTPASS_BASE_URL=https://sandbox.vtpass.com`
- [ ] Sandbox credentials in API `.env`
- [ ] `FEATURE_VTPASS=true`
- [ ] `php artisan paylity:vtpass-check` passes (when available)
- [ ] Manual ops fulfillment tested

---

## 10. Operator / Ops console

- [ ] `https://staging.paylity.ng/ops` reachable
- [ ] Operator key works for list + detail + manual fulfill
- [ ] Ops banner visible for staging/sandbox

---

## 11. Smoke tests

- [ ] Run [STAGING-SMOKE-TESTS.md](./STAGING-SMOKE-TESTS.md)
- [ ] Record results with date and tester name

---

## 12. Rollback plan

- [ ] **Vercel:** previous deployment ID documented for promote/rollback
- [ ] **cPanel API:** previous code tag/snapshot documented
- [ ] **Database:** cPanel backup taken before first migration
- [ ] Paystack callback/webhook URLs documented for revert

---

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Engineering | | | |
| Operations | | | |

**Staging Go:** All critical items checked, smoke tests pass, preflight clean.

---

## Optional reference

Nginx/systemd self-hosted setup: [VPS-ONLY-REFERENCE.md](./VPS-ONLY-REFERENCE.md) — **not required** for hybrid staging.
