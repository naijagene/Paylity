# Staging Deployment Checklist — PAYLITY NG RC1

Use this checklist when deploying **Release Candidate 1** to staging.

**Target URLs**

- Frontend: `https://staging.paylity.ng`
- API: `https://api-staging.paylity.ng`

---

## 1. Domain & DNS

- [ ] `staging.paylity.ng` A/AAAA or CNAME points to web server
- [ ] `api-staging.paylity.ng` A/AAAA or CNAME points to API server
- [ ] TTL appropriate for rollback window

## 2. SSL / TLS

- [ ] Valid certificate on frontend domain
- [ ] Valid certificate on API domain
- [ ] HTTPS redirect enforced
- [ ] HSTS considered (optional for staging)

## 3. API server (Laravel)

- [ ] PHP 8.2+ with required extensions
- [ ] Composer install (`--no-dev` for staging)
- [ ] `.env` populated from [STAGING-ENV-TEMPLATE.md](./STAGING-ENV-TEMPLATE.md)
- [ ] `php artisan key:generate` (first deploy only)
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache` and `route:cache`
- [ ] `php artisan paylity:preflight` passes (no FAIL items)
- [ ] Web server document root → `public/`
- [ ] `storage/` and `bootstrap/cache/` writable

## 4. Web server (Next.js)

- [ ] Node.js LTS installed on build host
- [ ] Frontend `.env.staging` values injected at build time
- [ ] `npm ci && npm run build`
- [ ] Process manager (PM2/systemd) or platform deploy configured
- [ ] Static assets served (`public/brand/paylity-logo.png`, favicons)

## 5. Database

- [ ] MySQL/PostgreSQL provisioned (avoid SQLite on staging)
- [ ] Credentials rotated from template placeholders
- [ ] Backups enabled
- [ ] Migrations applied successfully

## 6. Queue worker

- [ ] `QUEUE_CONNECTION` not `sync`
- [ ] Worker process running (`php artisan queue:work`)
- [ ] Failed jobs table monitored
- [ ] Restart worker after deploy

## 7. Scheduler / cron

- [ ] Cron entry: `* * * * * php /path/to/artisan schedule:run`
- [ ] Scheduled tasks verified (if any)

## 8. Storage & logs

- [ ] Log rotation configured
- [ ] Disk space alerts set
- [ ] No sensitive payloads logged in production mode

## 9. Environment configuration

- [ ] `APP_ENV=staging`
- [ ] `APP_DEBUG=false`
- [ ] `APP_VERSION=1.0.0-rc1`
- [ ] `APP_BUILD=2026.07.03-rc1`
- [ ] `FRONTEND_URL` matches frontend domain
- [ ] `OPERATOR_ACCESS_KEY` set and stored securely
- [ ] `FEATURE_VTPASS_AUTO_FULFILL=false` unless testing auto-delivery

## 10. Paystack (test mode)

- [ ] Test public/secret keys configured
- [ ] Callback URL → staging frontend `/payment/callback`
- [ ] Webhook URL → staging API webhook route
- [ ] Test payment completes end-to-end

## 11. VTPass (sandbox)

- [ ] Sandbox username/password/API key configured
- [ ] `FEATURE_VTPASS=true`
- [ ] `php artisan paylity:vtpass-check` passes (if available)
- [ ] Manual ops fulfillment tested

## 12. Operator / Ops console

- [ ] Ops URL reachable: `/ops`
- [ ] Operator key works for list + detail + manual fulfill
- [ ] Ops banner visible for sandbox/staging

## 13. Smoke tests

- [ ] Run [STAGING-SMOKE-TESTS.md](./STAGING-SMOKE-TESTS.md)
- [ ] Record results with date and tester name

## 14. Rollback plan

- [ ] Previous release tag/build number documented
- [ ] Database migration rollback strategy documented
- [ ] Frontend rollback = redeploy previous build artifact
- [ ] API rollback = redeploy previous release + `config:cache`
- [ ] Paystack webhook/callback URLs can be reverted quickly

---

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Engineering | | | |
| Operations | | | |

**Staging Go:** All critical items checked, smoke tests pass, preflight clean.
