# Daily Close Runbook (PAY-032)

## Command

```bash
php artisan paylity:financial-close --date=YYYY-MM-DD
php artisan paylity:financial-close --date=YYYY-MM-DD --dry-run
php artisan paylity:financial-close --date=YYYY-MM-DD --force
```

Scheduled daily at 01:00 (`financial_close_hour` setting available for future tuning).

## Snapshot metrics

- Gross collections, product value, convenience fee revenue
- Gateway fees charged / expected / actual
- Provider cost, gross margin
- Fulfilled / failed / paid-unfulfilled counts
- Paystack clearing balance, settlement difference
- Ledger imbalance count

## Status values

- `finalized` — clean close
- `finalized_with_exceptions` — paid-unfulfilled, ledger imbalance, or settlement difference
- Finalized snapshots are immutable unless `--force` rebuild

## Ops verification

Finance → Daily Financial Summary table
