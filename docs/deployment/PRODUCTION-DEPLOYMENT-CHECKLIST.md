# PAYLITY Production Deployment Checklist

Use this checklist before RC1 soft launch. Run all commands from `apps/api` unless noted.

## Pre-deploy validation

- [ ] `php artisan paylity:preflight` returns 0 FAIL
- [ ] `php artisan test` passes
- [ ] `npm run lint` and `npm run build` pass in `apps/web` and `apps/ops`
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_VERSION` and `APP_BUILD` set for the release
- [ ] `OPERATOR_ACCESS_KEY` configured and stored securely
- [ ] Paystack and VTPass credentials verified when integrations are enabled
- [ ] `SESSION_SECURE_COOKIE=true` in production
- [ ] Queue worker process configured if `QUEUE_CONNECTION` is not `sync`

## Database

- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=PlatformSettingsSeeder --force` (first deploy or when settings change)
- [ ] Product catalog seeded and validated

## Application release

- [ ] Deploy API code to production host
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Restart PHP-FPM / application process
- [ ] Restart queue workers after deploy

## Frontend release

- [ ] Deploy `apps/web` customer frontend
- [ ] Deploy `apps/ops` operations console
- [ ] `NEXT_PUBLIC_API_BASE_URL` points to production API
- [ ] `NEXT_PUBLIC_OPERATOR_API_BASE_URL` configured for ops console

## Post-deploy smoke tests

- [ ] `GET /api/v1/health` returns HTTP 200 with `status=ok`
- [ ] `GET /api/v1/platform/status` returns `checkout_enabled=true`
- [ ] Catalog endpoints return airtime, data, and electricity products
- [ ] Test checkout initialize for airtime (sandbox or live as appropriate)
- [ ] Ops console login and executive dashboard load
- [ ] Incident mode toggle tested and reverted to disabled

## Operational readiness

- [ ] Backup job verified (see `BACKUP-AND-RECOVERY.md`)
- [ ] Monitoring alerts configured for API health and queue failures
- [ ] On-call operator has ops console access key
- [ ] Incident mode and maintenance mode runbook reviewed

## Sign-off

| Role | Name | Date | Notes |
|------|------|------|-------|
| Engineering | | | |
| Operations | | | |
| Product | | | |
