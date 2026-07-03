# VTPass Certification Report — PAYLITY NG

**Document status:** Partial sandbox certification recorded  
**Last updated:** July 2026  
**Ticket:** PAY-015 / PAY-015C / PAY-015D

---

## Certification result

> **PARTIALLY CERTIFIED (sandbox)**

| Result | Criteria |
|--------|----------|
| NOT CERTIFIED | No sandbox tests completed or critical failures |
| PARTIALLY CERTIFIED | Some products verified; blockers remain |
| CERTIFIED | Airtime, data, electricity, verify, and ops fulfill validated in sandbox |

---

## Product certification status (sandbox)

| Area | Status | Notes |
|------|--------|-------|
| Airtime purchase | **CERTIFIED in sandbox** | Sandbox purchase fulfilled successfully |
| Electricity merchant verify | **CERTIFIED in sandbox** | Test meter verify succeeded |
| Data purchase | **PENDING valid variation code** | Use `php artisan paylity:vtpass-variations mtn-data` to fetch codes; set `VTPASS_TEST_DATA_VARIATION_CODE` and re-run integration tests |
| Invalid meter rejection | **SANDBOX-INCONCLUSIVE** | Sandbox may return verified for arbitrary meters; use `test_empty_meter_is_rejected_before_vtpass_api_call` for local validation |
| Invalid network | **CERTIFIED in sandbox** | Unsupported disco rejected before/at API |

---

## Environment

| Item | Value |
|------|-------|
| API environment | Local / staging |
| VTPass environment | Sandbox |
| Base URL | `https://sandbox.vtpass.com` |
| `FEATURE_VTPASS` | true (when running integration tests) |
| `FEATURE_VTPASS_AUTO_FULFILL` | false |
| Tester | _engineering_ |
| Test date | July 2026 |

---

## Credentials

| Check | Status | Notes |
|-------|--------|-------|
| Username configured | ☑ | |
| Password configured | ☑ | Not logged |
| API key configured | ☑ | Not logged |
| `paylity:vtpass-check` PASS | ☑ | Merchant verify passes with test meter |

---

## Connectivity

| Check | Status | Notes |
|-------|--------|-------|
| Base URL reachable | ☑ | |
| HTTP timeout configured | ☑ | Default 30s |
| Retry policy configured | ☑ | Default 2 retries |

---

## Authentication

| Check | Status | Notes |
|-------|--------|-------|
| Merchant verify accepted | ☑ | Test meter configured via `VTPASS_TEST_*` |
| Invalid credentials rejected | ☑ | 401 diagnostics via `paylity:vtpass-check` |

---

## Merchant Verify

| Check | Status | Notes |
|-------|--------|-------|
| Valid meter returns customer name | ☑ | Sandbox test meter |
| Meter number returned | ☑ | |
| Disco mapped correctly | ☑ | |
| Status mapped via `VTPassResponseMapper` | ☑ | |
| Unavailable when credentials missing | ☑ | Clear message shown |

---

## Airtime

| Check | Status | Reference | Notes |
|-------|--------|-----------|-------|
| Payload maps MTN → `mtn` | ☑ | | Unit tests |
| Sandbox purchase via ops fulfill | ☑ | PYL-SBOX-AIR-* | Integration test |
| Status → `fulfilled` | ☑ | | **CERTIFIED in sandbox** |

---

## Data

| Check | Status | Reference | Notes |
|-------|--------|-----------|-------|
| Variation code sent | ☐ | | Must use VTPass catalog code, not frontend plan ID |
| Sandbox purchase via ops fulfill | ☐ | | **CERTIFIED in sandbox** — set `VTPASS_TEST_DATA_VARIATION_CODE` |
| Status → `fulfilled` | ☐ | | Do not certify until integration test passes |

**Blocker:** Frontend plan IDs (e.g. `mtn-1gb-daily`) are not VTPass variation codes. Run `php artisan paylity:vtpass-variations mtn-data`, copy a valid `variation_code` into `VTPASS_TEST_DATA_VARIATION_CODE`, then re-run `php artisan test --testsuite=Integration`. Data stays **PENDING** until `test_sandbox_data_purchase` passes.

---

## Electricity

| Check | Status | Reference | Notes |
|-------|--------|-----------|-------|
| Merchant verify before fulfill | ☑ | | Backend service ready |
| Purchase payload includes meter fields | ☑ | | Unit tests |
| Sandbox purchase via ops fulfill | ☐ | | Not yet recorded in this run |
| Status → `fulfilled` | ☐ | | Merchant verify **CERTIFIED in sandbox** |

---

## Failures

| Scenario | Status | Notes |
|----------|--------|-------|
| Invalid meter → failed | **SANDBOX-INCONCLUSIVE** | Sandbox may verify `00000000000` |
| Empty meter → rejected locally | ☑ | No VTPass API call |
| Invalid disco → failed | ☑ | **CERTIFIED in sandbox** |
| Unpaid transaction → rejected | ☑ | Ops fulfill guard (feature tests) |
| Timeout handled safely | ☑ | Feature test with Http fake |

---

## Performance

| Metric | Target | Observed |
|--------|--------|----------|
| Merchant verify latency | < 10s | Within target (sandbox) |
| Purchase latency | < 30s | Airtime within target |
| Retry on transient failure | Yes | Configured |

---

## Observations

- Airtime sandbox fulfillment works end-to-end.
- Electricity merchant verify works with configured sandbox test meter.
- Data requires env-driven variation codes from `paylity:vtpass-variations` or VTPass `/api/service-variations` — frontend plan IDs are not valid VTPass codes.
- Invalid meter negative testing is unreliable in sandbox; local empty-meter validation covers malformed input.

---

## Known issues (at time of report)

1. Frontend checkout still uses mock meter verification UI — backend service ready but not wired to checkout.
2. Frontend `data_plan_id` values must be mapped to VTPass variation codes before production data launch.
3. Integration tests skip in CI unless `VTPASS_SANDBOX_TESTS=true`.
4. Data certification blocked until `VTPASS_TEST_DATA_VARIATION_CODE` is set (via `paylity:vtpass-variations`) and integration test passes.

---

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Engineering | | July 2026 | PARTIALLY CERTIFIED (sandbox) |
| Operations | | | |
| Founder | | | |

---

*Document: PAY-015 · VTPass Certification Report*
