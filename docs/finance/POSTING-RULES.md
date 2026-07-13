# Posting Rules (PAY-032)

## Payment success (`payment_received`)

When Paystack verification returns `success`:

| | Account | Amount |
| --- | --- | --- |
| **Debit** | `paystack_clearing` | `payable_amount × 100` kobo |
| **Credit** | `customer_funds_pending` | same |

No revenue is recognized at payment time.

## Gateway fee (`gateway_fee_recorded`)

Recorded alongside payment success using expected Paystack fee:

```
expected = payable_kobo × basis_points/10000 + flat_fee_kobo
```

| | Account | Amount |
| --- | --- | --- |
| **Debit** | `paystack_gateway_fee_expense` | expected |
| **Credit** | `settlement_payable` | expected |

Status is `provisional` until Paystack fee data is available on the transaction payload.

## Fulfillment (`customer_funds_recognized`)

When transaction status becomes `fulfilled`:

| | Account | Amount |
| --- | --- | --- |
| **Debit** | `customer_funds_pending` | product + convenience + gateway (kobo) |
| **Credit** | `vtpass_product_cost` | provider cost |
| **Credit** | `convenience_fee_revenue` | convenience fee |
| **Credit** | `gateway_fee_recovery` | gateway fee charged to customer |
| **Credit** | `product_margin_revenue` | `max(0, product − provider cost)` |

Provider cost resolution priority:
1. VTPass response amount
2. Catalog variation amount
3. Configured product cost
4. Product amount (provisional)

## Settlement received

Only when actual settlement data exists on the transaction:

| | Account | Amount |
| --- | --- | --- |
| **Debit** | `cash_adjustment` | actual settlement kobo |
| **Credit** | `paystack_clearing` | actual settlement kobo |

Differences route to `settlement_difference` via `settlement_difference_recorded`.

## Gross margin

```
gross_margin = product_kobo − provider_cost_kobo + convenience_fee_kobo + gateway_recovery_kobo − gateway_expense_kobo
```

## Corrections

Posted entries are immutable. Corrections use `reversal` event type (future/manual API-only in v1).
