# PAY-033B — Soft Launch Voucher & Viral Growth Engine

## Summary

PAY-033B adds a controlled launch voucher system for **airtime only**, integrated with checkout pricing, ledger accounting, ops marketing controls, post-fulfillment reviews, and viral sharing.

## Delivered

### Backend
- `launch_vouchers` and `launch_voucher_redemptions` tables
- `transaction_reviews` and `marketing_events` tables
- Transaction fields: `launch_voucher_id`, `voucher_code`, `voucher_discount_amount`
- `LaunchVoucherService` validation, reservation, and fulfillment redemption
- `POST /api/v1/vouchers/validate`
- Checkout initialize accepts `voucher_code` and `device_id`
- Ledger account `marketing_promotion_expense` with double-entry subsidy posting
- Ops marketing API under `/api/v1/ops/marketing/vouchers`
- Review and share endpoints on transactions
- Seeded vouchers: `PAYLITY500` (₦500) and `PAYLITY1000` (₦1,000)

### Frontend
- Airtime checkout voucher input with async validation
- Pricing breakdown includes voucher discount line
- Post-fulfillment review prompt (1–5 stars + optional comment)
- Viral share card (WhatsApp, Facebook, Telegram, X, Copy Link)
- Ops `/marketing` Launch Vouchers page with KPIs and enable/disable controls

### Analytics events
- `voucher.validated`
- `voucher.redeemed`
- `fulfillment.completed`
- `review.submitted`
- `share.initiated`

## Pricing rule

Voucher discount reduces **product amount only**.

```
payable = (product_amount - voucher_discount) + convenience_fee + gateway_fee
```

Gateway fee is calculated on the discounted product subtotal.

## Ledger rule

On fulfillment, customer-collected product funds are debited from `customer_funds_pending`, and the voucher subsidy is debited to `marketing_promotion_expense` while preserving existing revenue, provider cost, and margin lines.

## Launch docs

See also:
- `docs/launch/SOFT-LAUNCH-OPERATIONS.md`
- `docs/launch/PRODUCTION-SWITCH-RUNBOOK.md`

## Suggested commit

```
feat(marketing): add launch voucher, review and viral sharing engine
```
