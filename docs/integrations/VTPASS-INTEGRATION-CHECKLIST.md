# VTPass Integration Checklist — PAYLITY NG

Reference checklist for VTPass sandbox and live integration. Use with `php artisan paylity:vtpass-check` and the sandbox test steps document.

---

## Environment URLs

| Environment | Base URL |
|-------------|----------|
| Sandbox | `https://sandbox.vtpass.com` |
| Live | `https://vtpass.com` |

Configure via `VTPASS_BASE_URL` in `apps/api/.env`.

---

## Authentication

| Item | Value |
|------|-------|
| Method | HTTP Basic Auth (username + password) |
| Required header | `api-key: {VTPASS_API_KEY}` |
| Optional header | `public-key: {VTPASS_PUBLIC_KEY}` |
| Content type | JSON request/response |

Never log or expose `VTPASS_PASSWORD`, `VTPASS_API_KEY`, or `VTPASS_SECRET_KEY`.

---

## Required environment variables

| Variable | Purpose |
|----------|---------|
| `FEATURE_VTPASS` | Enable/disable fulfillment |
| `FEATURE_VTPASS_AUTO_FULFILL` | Auto-fulfill after payment (keep `false` for launch) |
| `VTPASS_BASE_URL` | Sandbox or live API base |
| `VTPASS_USERNAME` | Basic auth username |
| `VTPASS_PASSWORD` | Basic auth password |
| `VTPASS_API_KEY` | API key header |
| `VTPASS_PUBLIC_KEY` | Optional public key header |
| `VTPASS_TIMEOUT` | HTTP timeout seconds (default 30) |
| `VTPASS_RETRY_TIMES` | Retry attempts (default 2) |
| `VTPASS_RETRY_SLEEP_MS` | Delay between retries (default 500) |
| `VTPASS_TEST_DISCO` | Sandbox verify test disco (default IKEDC) |
| `VTPASS_TEST_METER_NUMBER` | Sandbox verify test meter |
| `VTPASS_TEST_METER_TYPE` | `prepaid` or `postpaid` |
| `VTPASS_SANDBOX_TESTS` | Enable integration test suite (default false) |

---

## Service ID mappings

### Airtime

| PAYLITY network | VTPass `serviceID` |
|-----------------|-------------------|
| MTN | `mtn` |
| Airtel | `airtel` |
| Glo | `glo` |
| 9mobile | `etisalat` |

### Data

| PAYLITY network | VTPass `serviceID` |
|-----------------|-------------------|
| MTN | `mtn-data` |
| Airtel | `airtel-data` |
| Glo | `glo-data` |
| 9mobile | `etisalat-data` |

Variation code comes from checkout `data_plan_id` (e.g. `mtn-1gb-daily`). Map to live VTPass catalog before production.

### Electricity

| PAYLITY disco | VTPass `serviceID` |
|---------------|-------------------|
| AEDC | `abuja-electric` |
| EKEDC | `ekedc` |
| IKEDC | `ikeja-electric` |
| PHED | `phed` |
| IBEDC | `ibedc` |
| KEDCO | `kedco` |

Meter type maps to `variation_code`: `prepaid` or `postpaid`.

---

## API endpoints

| Operation | Method | Path | Used by |
|-----------|--------|------|---------|
| Merchant Verify | POST | `/api/merchant-verify` | Electricity meter verification |
| Purchase | POST | `/api/pay` | Fulfillment (airtime, data, electricity) |
| Query / Requery | POST | `/api/requery` | Transaction status lookup (available, not yet wired to UI) |

### Merchant Verify payload

```json
{
  "serviceID": "ikeja-electric",
  "billersCode": "45053854956",
  "type": "prepaid"
}
```

Expected success fields: `Customer_Name`, `Meter_Number`, minimum amount fields in `content`.

### Purchase payload (electricity example)

```json
{
  "request_id": "PYL-20260703-ABC123-143022",
  "serviceID": "ikeja-electric",
  "billersCode": "45053854956",
  "variation_code": "prepaid",
  "amount": 5000,
  "phone": "08031234567"
}
```

---

## Retry strategy

- Connection failures: retry up to `VTPASS_RETRY_TIMES`
- VTPass retryable response codes (e.g. `030`–`035`, `040`): retry with backoff
- Non-retryable failures (e.g. `016`): fail immediately
- Parsed by `VTPassResponseMapper`

---

## Timeout strategy

- Default HTTP timeout: 30 seconds (`VTPASS_TIMEOUT`)
- On timeout: log safe metadata, throw `VTPassException` with code `VTPASS_TIMEOUT`
- Ops should requery before re-fulfilling

---

## Idempotency strategy

- Each fulfillment uses unique `request_id`: `{transaction.reference}-{HHmmss}`
- Do not reuse `request_id` for different purchase attempts
- Failed fulfillment retry generates new `request_id` on next fulfill call
- Requery uses original `request_id` from failed attempt (future enhancement)

---

## Known VTPass response codes

| Code | Meaning | Mapper status |
|------|---------|---------------|
| `000` | Success | success |
| `001`, `099` | Processing / pending | pending |
| `016` | Transaction failed | failed |
| `017`–`028` | Various failures | failed |
| `030`–`035`, `040` | Temporary / retryable | retryable |
| `041`–`050` | Validation / account errors | failed |
| Other | Unknown | unknown |

Full mapping: `apps/api/app/Services/Fulfillment/VTPassResponseMapper.php`

---

## Known failure scenarios

| Scenario | Expected behaviour |
|----------|-------------------|
| Invalid meter | Merchant verify returns failed; checkout mock remains until wired |
| Invalid network/disco | `UNSUPPORTED_DISCO` before API call |
| Missing credentials | Clear unavailable message; no API call |
| `FEATURE_VTPASS=false` | Fulfillment returns 503; verify returns unavailable |
| Timeout | Safe error; logged without secrets |
| Double fulfill | New `request_id`; ops must confirm payment first |

---

## Manual testing log

| Date | Tester | Product | Reference | VTPass request_id | Result | Notes |
|------|--------|---------|-----------|-------------------|--------|-------|
| | | | | | | |
| | | | | | | |
| | | | | | | |

---

## Final certification checklist

- [ ] Sandbox credentials obtained from VTPass
- [ ] `FEATURE_VTPASS=true` in staging `.env`
- [ ] `php artisan paylity:preflight` passes (no FAIL)
- [ ] `php artisan paylity:vtpass-check` passes (no FAIL)
- [ ] Merchant verify succeeds with sandbox test meter
- [ ] Airtime sandbox purchase fulfilled via ops console
- [ ] Data sandbox purchase fulfilled (valid variation code confirmed)
- [ ] Electricity sandbox purchase fulfilled after verify
- [ ] Invalid meter returns failed (not success)
- [ ] Logs contain no passwords, API keys, or secrets
- [ ] `VTPASS-CERTIFICATION-REPORT.md` updated with results
- [ ] Live credentials and service IDs validated before production toggle

---

*Document: PAY-015 · VTPass Integration Checklist*
