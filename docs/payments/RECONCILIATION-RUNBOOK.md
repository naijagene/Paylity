# Reconciliation Runbook

## Commands

### Payment reconciliation

```bash
php artisan paylity:reconcile-payments
php artisan paylity:reconcile-payments --reference=PYL-20260710-ABC123
php artisan paylity:reconcile-payments --since=2026-07-01 --limit=25
php artisan paylity:reconcile-payments --dry-run
```

### Fulfillment reconciliation

```bash
php artisan paylity:reconcile-fulfillments
php artisan paylity:reconcile-fulfillments --reference=PYL-20260710-ABC123
php artisan paylity:reconcile-fulfillments --dry-run
```

## Scheduler

Single cron entry on one server:

```
* * * * * php artisan schedule:run
```

Scheduled jobs (with `withoutOverlapping` + `onOneServer`):

- `paylity:reconcile-payments` — every 10 minutes
- `paylity:reconcile-fulfillments` — every 10 minutes
- `paylity:process-fulfillment-retries` — every 5 minutes

## Ops Console

Navigate to **Reconciliation** for queues and safe actions:

- Reconcile payment
- Reconcile fulfillment
- Retry confirmed failure
- Resume automation
- View transaction timeline

## Emergency

1. Pause auto-fulfill feature flag if provider incident
2. Run `--dry-run` first to inspect scope
3. Reconcile single reference before batch repair
4. Escalate amount/provider mismatches to manual review — never force-deliver
