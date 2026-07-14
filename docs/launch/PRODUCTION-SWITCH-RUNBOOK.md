# PAY-033 Production Switch Runbook

See Phase A–D steps in this document. Validated staging reference: `PYL-20260713-TPHEEX`.

## Paystack URLs (actual routes)

- Callback: `https://<customer-domain>/payment/callback`
- Webhook: `https://<api-domain>/api/v1/payments/paystack/webhook`

## Commands

`paylity:launch-preflight`, `paylity:database-fingerprint`, `paylity:paystack-mode`, `paylity:vtpass-mode`, `paylity:pricing-audit`, `paylity:backup-database`, `paylity:backup-verify`
