# Settlement Runbook (PAY-032)

## Commands

```bash
# Preview
php artisan paylity:reconcile-settlements --dry-run --date=2026-07-12

# Apply when actual settlement data exists on transactions
php artisan paylity:reconcile-settlements --date=2026-07-12 --limit=50

# Single reference
php artisan paylity:reconcile-settlements --reference=PYL-20260712-ABC123
```

## Rules

- Settlement received is posted only when `response_payload.verify.settlement_amount` (or equivalent) is present.
- Never infer bank settlement without actual data.
- Differences post to `settlement_difference`.
- Threshold alerts use `financial_settlement_difference_threshold` (default ₦500).

## Ops

- Finance → Settlement Exceptions table
- Dry reconcile via Finance dashboard button or API `POST /ops/finance/reconcile-settlements?dry_run=1`
