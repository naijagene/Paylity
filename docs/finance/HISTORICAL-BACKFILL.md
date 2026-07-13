# Historical Backfill (PAY-032)

## Command

```bash
php artisan paylity:ledger-backfill --dry-run --limit=50
php artisan paylity:ledger-backfill --limit=50
php artisan paylity:ledger-backfill --reference=PYL-20260710-ABC123
php artisan paylity:ledger-backfill --since=2026-07-01 --limit=100
php artisan paylity:ledger-backfill --date=2026-07-01 --limit=100
```

## Classification

| Transaction state | Posting |
| --- | --- |
| Payment not completed | Skip |
| Payment success, unfulfilled | Payment posting only |
| Fulfilled | Payment + fulfillment recognition |
| Failed fulfillment + paid | Payment only (pending liability remains) |
| Cancelled before payment | Skip |
| Manual review | Skip unless confirmed facts only |

## Idempotency

Repeated backfill increments `already_posted`; no duplicate ledger transactions.

## First deployment

Always dry-run first. Use bounded `--limit` on first repair — never unbounded backfill on first deploy.
