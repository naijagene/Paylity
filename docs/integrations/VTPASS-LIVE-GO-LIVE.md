# VTPass Live Go-Live Guide

This guide prepares PAYLITY to switch from VTPass sandbox to live production safely while keeping sandbox support for staging.

## Environment separation

| Environment | `VTPASS_ENV` | Base URL | Credentials |
|-------------|--------------|----------|-------------|
| Sandbox / staging | `sandbox` | `https://sandbox.vtpass.com` | Sandbox account keys |
| Live production | `production` | `https://vtpass.com` | Live account keys |

Set credentials only in deployment environment variables. Never commit live keys to the repository.

### Required variables

```env
VTPASS_ENV=production
VTPASS_BASE_URL=https://vtpass.com
VTPASS_USERNAME=
VTPASS_PASSWORD=
VTPASS_API_KEY=
VTPASS_PUBLIC_KEY=
VTPASS_SECRET_KEY=
FEATURE_VTPASS=true
FEATURE_VTPASS_AUTO_FULFILL=false
```

Keep `FEATURE_VTPASS_AUTO_FULFILL=false` until live certification is complete and operations sign off.

## Production readiness checks

Run before switching traffic to live VTPass:

```bash
php artisan paylity:preflight
php artisan paylity:vtpass-check
```

Preflight validates:

- `VTPASS_ENV` matches `VTPASS_BASE_URL`
- Username, password, API key
- Public and secret keys in production
- Auto-fulfill policy warnings

`paylity:vtpass-check` additionally validates reachability, wallet balance (when `VTPASS_PUBLIC_KEY` is set), and merchant verify connectivity.

## Wallet balance

Automated balance checks use `GET /api/balance` with `api-key` and `public-key` headers.

If the balance API is unavailable:

1. Log into the VTPass live dashboard
2. Confirm wallet funding manually
3. Record the funded balance in the go-live report
4. Monitor ops dashboard balance field — it will show the manual-check message when unavailable

## Live safety mode

System settings (ops platform page or `/api/v1/settings`):

| Setting | Default | Purpose |
|---------|---------|---------|
| `vtpass_live_safety_mode` | `true` | Limits live fulfillment to small test amounts |
| `vtpass_live_test_max_amount` | `500` | Maximum product amount (NGN) per live fulfillment |

When safety mode is active in production:

- Fulfillment above the threshold returns `VTPASS_LIVE_SAFETY_LIMIT`
- Ops dashboard shows a warning alert
- Customer-facing message is sanitized and non-technical

Disable safety mode only after successful live smoke tests and operations approval.

## Product certification matrix

Control live readiness per product using feature flags:

| Flag | Product | Recommended initial live state |
|------|---------|--------------------------------|
| `service_airtime_enabled` | Customer airtime checkout | `true` |
| `provider_vtpass_airtime_enabled` | VTPass airtime fulfillment | `true` after live airtime test |
| `service_data_enabled` | Customer data checkout | `true` |
| `provider_vtpass_data_enabled` | VTPass data fulfillment | `false` until data certified |
| `service_electricity_enabled` | Customer electricity checkout | `true` |
| `provider_vtpass_electricity_enabled` | VTPass electricity fulfillment | `true` after live electricity test |

Both service and provider flags must be enabled for fulfillment to proceed.

Current sandbox certification status is documented in `docs/integrations/VTPASS-CERTIFICATION-REPORT.md`.

## Manual live smoke checklist

Do not automate these in CI. Run manually against production with safety mode enabled.

- [ ] Preflight and `paylity:vtpass-check` pass with `VTPASS_ENV=production`
- [ ] Ops dashboard shows `environment=production`, wallet balance, safety mode active
- [ ] ₦100 MTN airtime purchase — payment success, manual fulfill, receipt verified
- [ ] Small MTN data plan (only when `provider_vtpass_data_enabled=true`)
- [ ] Small electricity token test on a known-safe meter (only when certified)
- [ ] Failed purchase handling — VTPass failure reason stored, customer receipt available
- [ ] Retry fulfillment from ops console succeeds for retryable failures
- [ ] Transaction event trail shows fulfillment pending → fulfilled/failed
- [ ] Logs contain request reference and masked billersCode, no credentials

## Ops controls

The operations dashboard exposes:

- VTPass environment (`sandbox` / `production`)
- Provider status and host
- Wallet balance (when API available)
- Auto-fulfill enabled/disabled
- Live safety mode and max test amount
- Product readiness matrix (airtime, data, electricity)

## Rollback to sandbox / offline

1. Set `FEATURE_VTPASS=false` to stop all fulfillment immediately
2. Or revert to sandbox:
   - `VTPASS_ENV=sandbox`
   - `VTPASS_BASE_URL=https://sandbox.vtpass.com`
   - Replace credentials with sandbox keys
3. Enable `incident_mode` if checkout must pause during provider issues
4. Clear ops dashboard alerts after rollback confirmation
5. Document rollback time and reason in `docs/release/GO_LIVE_REPORT.md`

## Incident procedure

If live VTPass is degraded:

1. Enable `incident_mode` to pause new checkout
2. Set `FEATURE_VTPASS_AUTO_FULFILL=false`
3. Disable affected `provider_vtpass_*` flags for failing product lines
4. Retry pending fulfillments manually after provider recovery
5. Keep safety mode enabled until root cause is confirmed resolved

## Logging and audit

Live VTPass requests log:

- Environment (`sandbox` / `production`)
- `request_id` / reference
- `serviceID`, masked `variation_code`, masked `billersCode`
- Response code and duration
- No username, password, API key, or secret key values

Fulfillment attempts and transaction events retain the full audit trail in the database.

## Related docs

- `docs/deployment/PRODUCTION-ENVIRONMENT.md`
- `docs/deployment/GO-LIVE-SMOKE-TESTS.md`
- `docs/integrations/VTPASS-INTEGRATION-CHECKLIST.md`
- `docs/integrations/VTPASS-SANDBOX-TEST-STEPS.md`
- `docs/release/GO_LIVE_REPORT.md`
