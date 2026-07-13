# PAYLITY Ledger Architecture (PAY-032)

Launch-minimum immutable double-entry ledger for financial accountability.

## Core tables

| Table | Purpose |
| --- | --- |
| `ledger_accounts` | Seeded chart of accounts (code-driven, no operator-created accounts in v1) |
| `ledger_transactions` | Balanced posting header with idempotency key |
| `ledger_entries` | Debit/credit lines (amounts in **kobo**, currency `NGN`) |
| `transaction_financials` | Per-transaction provider cost, margin, gateway fee snapshot |
| `settlement_batches` / `settlement_items` | Paystack settlement reconciliation |
| `daily_financial_snapshots` | Immutable daily close metrics |

## Money units

- Transaction commercial fields (`product_amount`, `payable_amount`, etc.) remain **whole-naira integers** (existing PAYLITY convention).
- Ledger entries store **kobo** (`naira × 100`) via `App\Support\Finance\Money`.

## Idempotency

Each posting uses `idempotency_key = txn:{transaction_id}:{event_type}` with a unique DB constraint. Duplicate webhook, retry, reconciliation, or backfill calls return the existing posting.

## Account map

### Assets
- `paystack_clearing` — collections awaiting settlement
- `vtpass_wallet_asset` — provider wallet consumption (reserved)
- `cash_adjustment` — bank settlement received

### Liabilities
- `customer_funds_pending` — collected funds pending fulfillment recognition
- `provider_payable` — provider obligations
- `settlement_payable` — expected gateway/settlement obligations

### Revenue
- `convenience_fee_revenue`
- `gateway_fee_recovery`
- `product_margin_revenue`

### Expenses
- `paystack_gateway_fee_expense`
- `vtpass_product_cost`
- `reconciliation_adjustment_expense`

### Control
- `suspense`
- `settlement_difference`

## Posting hooks

| Event | Trigger |
| --- | --- |
| `payment_received` | `PaymentVerificationService` on Paystack success |
| `gateway_fee_recorded` | Same payment hook (expected fee, provisional until actual known) |
| `customer_funds_recognized` | `FulfillmentService::markFulfilled()` |

## Commands

```bash
php artisan paylity:ledger-backfill --dry-run --limit=50
php artisan paylity:reconcile-settlements --dry-run
php artisan paylity:financial-close --date=YYYY-MM-DD --dry-run
php artisan paylity:financial-alert-scan
```

## Ops

- `/finance` dashboard (read-only inspection)
- Transaction detail includes `finance` summary + ledger history
- No edit/delete/force-balance actions in v1
