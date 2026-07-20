# Live Payment Smoke Test

Quick smoke sequence after deploying PAY-035 live payment controls.

## 1. Mode safety

```bash
php artisan paylity:paystack-mode --json
```

Confirm:

- Keys are aligned (`test` in staging, `live` in production)
- No mixed configuration
- Callback and webhook URLs present

## 2. Read-only preflight

```bash
php artisan paylity:payment-live-preflight --json
php artisan paylity:payment-live-preflight --strict --json
```

Confirm verdict is not `BLOCKED` in the target environment before enabling live checkout.

## 3. Soft launch limits

In Ops Go-Live Center confirm:

- Daily transaction usage visible
- Daily revenue usage visible
- Launch mode is `soft_launch`

Attempt checkout above configured caps and confirm API returns:

- `DAILY_TRANSACTION_LIMIT_REACHED`
- `DAILY_REVENUE_LIMIT_REACHED`
- `AMOUNT_EXCEEDS_SOFT_LAUNCH_LIMIT`

## 4. Maintenance safety

Enter maintenance mode with confirmation.

Confirm:

- New checkout initialization is blocked with `LAUNCH_MODE_MAINTENANCE`
- Existing webhook processing still accepts signed Paystack events
- Paid transaction recovery remains available in Ops

Restore soft launch afterward.

## 5. Certification session

1. Create certification session (₦100 airtime)
2. Complete one real customer checkout
3. Link transaction reference
4. Refresh certification evidence
5. Export evidence JSON and verify it contains no secrets

## 6. Rollback drill

```bash
php artisan paylity:payment-live-rollback --maintenance --confirm=ENTER-MAINTENANCE
php artisan paylity:payment-live-rollback --soft-launch --confirm=RESTORE-SOFT-LAUNCH
```

Confirm audit events were written.

## Expected healthy outputs

| Command | Healthy signal |
| --- | --- |
| `paylity:paystack-mode` | exit 0, `verdict=valid` |
| `paylity:payment-live-preflight` | `READY` or `READY_WITH_WARNINGS` |
| `paylity:payment-certify-live --finalize` | `CERTIFIED` or `CERTIFIED_WITH_WARNINGS` after real transaction |

## Known non-blockers in staging

- Paystack test keys fail strict live preflight by design
- Missing recent financial close may warn outside production
- VTPass sandbox mode warns until live credentials are configured
