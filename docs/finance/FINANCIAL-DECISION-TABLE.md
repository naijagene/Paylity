# Financial Decision Table (PAY-032)

| Situation | Action | Account impact |
| --- | --- | --- |
| Paystack success | Post `payment_received` | Dr clearing, Cr customer_funds_pending |
| Paystack failed | No ledger posting | — |
| Fulfilled | Post `customer_funds_recognized` | Release liability; recognize cost + revenue |
| Provider cost unknown | Use product amount (provisional) | Flag `provider_cost_status=provisional` |
| Actual Paystack fee unknown | Expected fee (provisional) | `gateway_fee_recorded` |
| Settlement actual known | Post `settlement_received` | Dr cash_adjustment, Cr clearing |
| Under-settlement | Post `settlement_difference_recorded` | Route variance to control |
| Duplicate webhook/retry | Idempotency key dedupes | No second posting |
| Manual adjustment (v1) | API-only / deferred UI | Requires reason + audit |
| Ledger imbalance | Alert `LEDGER_IMBALANCE` | Escalate to manual review |

## Deferred v1

Customer wallets, multi-currency, automated bank import, full refund automation, ERP integration.
