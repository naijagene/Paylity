# PAY-033 Report — Production Switch and Go-Live Readiness

## Summary

PAY-033 adds production switch validation, launch safeguards, pricing fix for negative margin, Go-Live Ops center, backup tooling, and runbooks. No new customer features. Historical ledger entries unchanged.

## Root cause — negative margin (staging `PYL-20260713-TPHEEX`)

**Cause:** Checkout charged ₦0 gateway fee while Paystack fee (~₦117) was expensed in ledger. ₦100 convenience fee did not cover gateway cost.

**Fix (Policy A):** Pass iterative Paystack gateway fee to customer at checkout. Frontend and backend now agree. All standard launch amounts show positive estimated margin (~₦100 convenience retained).

## Key files

### API services
- `app/Services/Finance/PaystackGatewayFeeCalculator.php`
- `app/Services/FeeService.php` (gateway fee pass-through)
- `app/Services/Launch/*` (preflight, backup, heartbeat, launch mode, pricing audit)
- `app/Services/Ops/OpsGoLiveService.php`

### Commands
- `paylity:launch-preflight`
- `paylity:database-fingerprint`
- `paylity:paystack-mode`
- `paylity:vtpass-mode`
- `paylity:pricing-audit`
- `paylity:backup-database`
- `paylity:backup-verify`

### Ops
- `apps/ops/src/app/go-live/page.tsx`
- `apps/ops/src/components/go-live/GoLiveClient.tsx`
- `GET/POST /api/v1/ops/go-live/*`

### Web pricing
- `apps/web/src/lib/checkout/pricing.ts`
- `apps/web/src/hooks/useCheckoutState.ts`

### Tests
- `tests/Feature/Console/Pay033LaunchReadinessTest.php`
- `apps/web/src/lib/checkout/pricing.test.ts`

## Deployment checklist

### A. Files to deploy
All modified files under `apps/api`, `apps/ops`, `apps/web`, `docs/launch/`.

### B. Migrations
None new in PAY-033 (uses existing platform settings table).

### C. Seeders
`PlatformSettingsSeeder` (launch_mode, caps, support fields).

### D. Environment variables
See `docs/launch/PRODUCTION-ENVIRONMENT.md`. No secrets in repo.

### E. Domains/DNS
See `docs/launch/DOMAIN-SSL-CORS.md`.

### F. Paystack dashboard
- Callback: `https://<customer-domain>/payment/callback`
- Webhook: `https://<api-domain>/api/v1/payments/paystack/webhook`

### G. VTPass
Live credentials, `VTPASS_ENV=production`, `VTPASS_SANDBOX_TESTS=false`.

### H. Cron
`* * * * * cd /path/to/apps/api && php artisan schedule:run`

### I. Backup
`php artisan paylity:backup-database` then `paylity:backup-verify`

### J. Preflight
`php artisan paylity:launch-preflight --environment=production --strict`

### K. Smoke transaction
Minimum airtime purchase in `soft_launch` mode; verify ledger + Finance Center.

### L. Ops verification
`/go-live`, `/finance`, `/reconciliation`, `/monitoring`

### M. Rollback
`launch_mode=maintenance`; see `docs/launch/PRODUCTION-ROLLBACK.md`

## Suggested commit

`feat(launch): add production switch and go-live controls`
