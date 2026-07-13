# Scheduler Operations

## Production cron

Run **once per server** (or once in cluster with `onOneServer`):

```
* * * * * cd /path/to/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

Do not schedule individual artisan commands separately.

## PAY-031 scheduled commands

| Command | Interval | Overlap lock |
|---------|----------|--------------|
| `paylity:reconcile-payments` | 10 min | 15 min |
| `paylity:reconcile-fulfillments` | 10 min | 15 min |
| `paylity:process-fulfillment-retries` | 5 min | 10 min |
| `paylity:cleanup-otp` | Daily 02:30 | onOneServer |
| `paylity:cleanup-webhooks` | Weekly Sun 03:00 | onOneServer |

## Verification

```bash
php artisan schedule:list
```

## Rollback

Remove `paylity:reconcile-fulfillments` from schedule if needed; payment reconciliation and retries remain independent.
