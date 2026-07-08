# Production Rollback Guide

## When to rollback

Rollback when a deploy causes:

- Sustained API `503` responses on `/api/v1/health`
- Checkout initialization failures above baseline
- Payment callback or webhook processing errors
- Data corruption or failed migrations

## Fast rollback (application only)

1. Re-deploy the previous known-good API release artifact.
2. Re-deploy the matching `apps/web` and `apps/ops` builds.
3. Run `php artisan config:cache` and restart PHP-FPM.
4. Restart queue workers.
5. Verify `/api/v1/health` and `/api/v1/platform/status`.
6. Enable maintenance mode from the ops console if customer impact continues.

## Database rollback

- Prefer forward-fix migrations over reversing schema changes in production.
- If a migration must be rolled back, restore from the latest verified backup instead of running `migrate:rollback` on live data unless explicitly tested.
- Document the incident and migration state before any schema change.

## Provider rollback

- Disable Paystack or VTPass feature flags in ops if a provider regression is isolated.
- Enable incident mode to pause checkout while provider issues are investigated.

## Verification after rollback

- [ ] Health endpoint healthy
- [ ] Checkout initialize succeeds for airtime
- [ ] Ops monitoring shows normal queue metrics
- [ ] No new failed jobs accumulating
- [ ] Customer banner cleared (incident/maintenance disabled)

## Communication

1. Enable incident mode and confirm homepage banner text.
2. Notify operators and stakeholders.
3. Record timeline, root cause, and follow-up actions in the ops runbook.
