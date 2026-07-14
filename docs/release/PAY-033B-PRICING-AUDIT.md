# PAY-033B Pricing Audit — Voucher Gateway Fee Recovery

**Ticket:** PAY-033B-AUDIT  
**Date:** 2026-07-14  
**Verdict:** **PASS — no pricing fix required**

## Summary

The reported risk — that gateway fee might be calculated only from `discounted_product_amount` — does **not** apply to the current implementation. Both backend and frontend recover the Paystack gateway fee iteratively against:

```
discounted_product_amount = max(0, product_amount - voucher_discount)
pre_gateway_charge        = discounted_product_amount + convenience_fee
gateway_fee               = iterative Paystack recovery on (pre_gateway_charge + gateway_fee)
payable_amount            = pre_gateway_charge + gateway_fee
```

When a voucher fully covers the product amount, `discounted_product_amount = 0` but `pre_gateway_charge = ₦100` (convenience fee). Gateway fee is correctly calculated on ₦100, not ₦0.

## Pricing Trace

| Layer | File | Behavior |
|-------|------|----------|
| Quote | `FeeService::quote()` | Computes `pre_gateway_charge`, passes `(net_product, convenience)` to gateway calculator |
| Gateway | `PaystackGatewayFeeCalculator::feeKoboForCheckout()` | Iterates on `subtotal = product + convenience` |
| Voucher validation | `LaunchVoucherService::validateForCheckout()` | Uses `FeeService::quote()`, rejects `payable <= 0` |
| Checkout init | `TransactionService` | Persists quote fields on transaction |
| Paystack | `PaystackService::initializeTransaction()` | Charges `payable_amount * 100` kobo |
| Frontend | `pricing.ts::calculatePricingWithVoucher()` | Mirrors backend iterative logic |
| Ledger | `LedgerPostingService::postFulfillmentRecognized()` | Marketing expense = voucher subsidy; provider cost = full face value |

## Scenario Results (Paystack enabled, 1.5% + ₦100 flat)

| Scenario | Product | Voucher | Discounted Product | Pre-Gateway | Convenience | Gateway | Payable | Paystack Charge | Est. Paystack Fee | Est. Margin |
|----------|---------|---------|-------------------|-------------|-------------|---------|---------|-----------------|-------------------|-------------|
| Full cover (small) | ₦500 | ₦500 | ₦0 | ₦100 | ₦100 | ₦103 | ₦203 | ₦20,300 | ₦103.05 | ₦99.95 |
| Partial cover | ₦1,000 | ₦500 | ₦500 | ₦600 | ₦100 | ₦111 | ₦711 | ₦71,100 | ₦110.67 | ₦100.33 |
| Full cover (large) | ₦1,000 | ₦1,000 | ₦0 | ₦100 | ₦100 | ₦103 | ₦203 | ₦20,300 | ₦103.05 | ₦99.95 |
| Half cover | ₦2,000 | ₦1,000 | ₦1,000 | ₦1,100 | ₦100 | ₦118 | ₦1,218 | ₦121,800 | ₦118.27 | ₦99.73 |

**Wrong-path comparison:** If gateway were calculated on `discounted_product` alone (convenience = 0), the ₦500+₦500 scenario would under-recover by ₦1 (₦102 vs ₦103). The implementation avoids this.

## Rejection Scenarios

| Scenario | Error Code |
|----------|------------|
| Expired voucher | `VOUCHER_EXPIRED` |
| Exhausted voucher | `VOUCHER_EXHAUSTED` |
| Invalid code | `VOUCHER_NOT_FOUND` |
| Duplicate phone | `VOUCHER_PHONE_USED` |
| Duplicate device | `VOUCHER_DEVICE_USED` |

## Ledger Posting (Voucher)

For a ₦1,000 airtime + ₦500 voucher + Paystack checkout:

- **Debit** `customer_funds_pending`: ₦500 collected product + ₦100 convenience + ₦111 gateway
- **Debit** `marketing_promotion_expense`: ₦500 voucher subsidy
- **Credit** `vtpass_product_cost`: ₦1,000 full airtime face value
- **Credit** `convenience_fee_revenue`: ₦100
- **Credit** `gateway_fee_recovery`: ₦111
- Debits = credits; repeated fulfillment posting is idempotent (no duplicate marketing expense)

## Tests Added

- `apps/api/tests/Feature/Api/V1/Pay033bVoucherPricingAuditTest.php`
- `apps/web/src/lib/checkout/pricing.test.ts` (voucher scenario parity)

## Deployment Files

No migration required. Deploy these application files:

- `apps/api/app/Services/FeeService.php`
- `apps/api/app/Services/Finance/PaystackGatewayFeeCalculator.php`
- `apps/api/app/Services/Marketing/LaunchVoucherService.php`
- `apps/api/app/Services/Finance/LedgerPostingService.php`
- `apps/web/src/lib/checkout/pricing.ts`
- `apps/api/tests/Feature/Api/V1/Pay033bVoucherPricingAuditTest.php`
- `apps/web/src/lib/checkout/pricing.test.ts`

## Smoke Test Steps

1. Enable Paystack in staging (`services.paystack.enabled = true`).
2. Open airtime checkout, enter ₦500 product amount.
3. Apply `PAYLITY500` — confirm payable = **₦203** (₦0 product + ₦100 convenience + ₦103 gateway).
4. Complete Paystack payment — verify charge = ₦203.00.
5. Repeat with ₦1,000 + `PAYLITY500` — payable = **₦711**.
6. Confirm receipt shows product ₦1,000, voucher −₦500, convenience ₦100, gateway ₦111, total ₦711.
7. In ops ledger, confirm `marketing_promotion_expense` = ₦500 for that transaction.
8. Apply expired/used voucher — confirm structured error, no checkout created.

## Root Cause

**Gateway pricing:** None. The PAY-033B implementation report wording was ambiguous (“gateway fee calculated on discounted product subtotal”), but the code correctly includes convenience fee in the pre-gateway base passed to the iterative calculator.

**Ledger posting (found during audit):** Full-voucher checkouts (`collected_product = 0`) attempted to post a zero-amount debit line. Fixed by omitting the collected-product debit when amount is zero.
