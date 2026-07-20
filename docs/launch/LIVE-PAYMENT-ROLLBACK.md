# Live Payment Rollback

Use this runbook for emergency rollback during Paystack live cutover.

## What rollback does

- Switches launch mode to `maintenance` or `soft_launch`
- Records operator audit evidence
- Preserves all transactions and successful payments
- Keeps callback and webhook processing enabled
- Allows fulfillment recovery for already-paid transactions

## What rollback does not do

- Delete transactions
- Reverse successful Paystack payments automatically
- Disable webhook or callback routes
- Block paid-transaction recovery workflows

## Maintenance rollback

Blocks new checkout initialization only.

```bash
php artisan paylity:payment-live-rollback --maintenance --confirm=ENTER-MAINTENANCE
```

Ops equivalent:

1. Open Go-Live Center
2. Confirm maintenance dialog
3. Click **Enter Maintenance Mode**

## Restore soft launch

Re-enable controlled checkout with daily caps.

```bash
php artisan paylity:payment-live-rollback --soft-launch --confirm=RESTORE-SOFT-LAUNCH
```

Ops equivalent:

1. Open Go-Live Center
2. Click **Soft Launch Mode**

## After rollback

1. Review in-flight transactions in Ops Transactions and Reconciliation
2. Confirm webhook processing remains healthy
3. Retry fulfillment recovery for paid but unfulfilled transactions if needed
4. Run:

```bash
php artisan paylity:payment-live-preflight --strict
```

5. Do not claim production certification until a fresh live certification session succeeds

## Audit evidence

Each rollback creates `launch_audit_events` rows for:

- `live_payment_rollback`
- `maintenance_mode_entered` or `soft_launch_restored`

Capture operator name, timestamp, previous mode, and new mode from Ops export or database audit table.
