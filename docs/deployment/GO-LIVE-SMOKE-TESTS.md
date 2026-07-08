# Go-Live Smoke Tests

Run after staging or production deploy. Automated coverage exists in `apps/api/tests/Feature/Api/V1/GoLiveSmokeTest.php`.

## API smoke tests

```bash
cd apps/api
php artisan test --filter=GoLiveSmokeTest
```

### Manual verification

| Area | Endpoint / action | Expected |
|------|-------------------|----------|
| Health | `GET /api/v1/health` | HTTP 200, `status=ok` |
| Platform status | `GET /api/v1/platform/status` | `checkout_enabled=true` |
| Catalog | `GET /api/v1/catalog/products` | Airtime, data, electricity |
| Airtime checkout | `POST /api/v1/checkout/initialize` | HTTP 201 |
| OTP | `POST /api/v1/otp/request` | HTTP 201 |
| Payment verify route | `GET /api/v1/payments/paystack/verify/{ref}` | Structured response |
| Receipt | `GET /api/v1/transactions/{ref}/receipt` | Receipt payload |
| Verification | `GET /api/v1/receipts/verify/{token}` | 404 for invalid token |
| History | `GET /api/v1/transactions?phone=` | HTTP 200 |
| Monitoring | `GET /api/v1/ops/monitoring` | Queue metrics present |
| Ops dashboard | `GET /api/v1/ops/dashboard` | VTPass environment, balance, safety mode |
| Reports | `GET /api/v1/ops/reports/daily-reconciliation` | Summary payload |

## VTPass live smoke tests (manual only)

Run only when switching to `VTPASS_ENV=production`. Do not add to CI.

See `docs/integrations/VTPASS-LIVE-GO-LIVE.md` for the full checklist. Minimum manual checks:

| Step | Action | Expected |
|------|--------|----------|
| Preflight | `php artisan paylity:preflight` | VTPass environment PASS |
| VTPass check | `php artisan paylity:vtpass-check` | Reachability + balance WARN/PASS |
| Safety mode | Ops dashboard | `live_safety_mode=true`, max ₦500 |
| Small airtime | ₦100 live purchase + manual fulfill | Fulfilled, receipt OK |
| Product gate | Disable `provider_vtpass_data_enabled` | Fulfillment blocked with `VTPASS_PRODUCT_NOT_READY` |
| Rollback | Set `FEATURE_VTPASS=false` | Ops fulfill returns `VTPASS_DISABLED` |

## Frontend smoke tests

### Customer app (`apps/web`)

- [ ] Homepage loads with service cards
- [ ] Incident banner hidden when platform healthy
- [ ] Airtime checkout form loads catalog networks
- [ ] Data checkout loads visible plans
- [ ] Electricity checkout loads discos
- [ ] Checkout blocked when incident mode enabled (ops toggle)

### Ops console (`apps/ops`)

- [ ] Operator key gate accepts valid key
- [ ] Executive dashboard KPIs load
- [ ] VTPass live readiness panel shows environment, balance, safety mode
- [ ] Incident warning banner when incident mode on
- [ ] Platform page toggles maintenance and incident mode
- [ ] Reports page exports CSV

## Security checks

- [ ] API responses include security headers
- [ ] `php artisan paylity:preflight` passes
- [ ] Rate limit returns 429 JSON envelope on checkout abuse

## Build verification

```bash
cd apps/web && npm run lint && npm run build
cd apps/ops && npm run lint && npm run build
cd apps/api && php artisan test
```
