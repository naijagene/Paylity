# VTPass Certification Report — PAYLITY NG

**Document status:** Partial sandbox certification recorded  
**Last updated:** July 2026  
**Ticket:** PAY-015 / PAY-015C–H

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
| Electricity purchase | **CERTIFIED in sandbox** | `test_sandbox_electricity_purchase` passes with token/unit fields logged |
| Data purchase | **PENDING** | Latest failure: `TRANSACTION FAILED` — use integration test diagnostics to inspect variation code, phone, sandbox balance |
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
| Variation code sent | ☑ | | Via `VTPASS_TEST_DATA_VARIATION_CODE` |
| Alternate variation supported | ☑ | | Optional `VTPASS_TEST_DATA_VARIATION_CODE_ALT` |
| Sandbox purchase via FulfillmentService | ☐ | PYL-SBOX-DATA-* | **PENDING** — fails with generic `TRANSACTION FAILED` |
| Status → `fulfilled` | ☐ | | Do not certify until integration test passes |
| Failure diagnostics | ☑ | | Integration test prints sanitized `response_payload`, codes, and content errors |

**Latest failure reason:** `TRANSACTION FAILED` (VTPass code `016`). Run `test_sandbox_data_purchase` with sandbox flags enabled to print sanitized diagnostics including `serviceID`, `variation_code`, `phone`, `request_id`, and nested `content` errors. Try `VTPASS_TEST_DATA_VARIATION_CODE_ALT` if the primary code fails. Investigate variation catalog match, recipient phone validity, and sandbox wallet balance.

---

## Electricity purchase

| Check | Status | Reference | Notes |
|-------|--------|-----------|-------|
| Merchant verify before purchase | ☑ | | `test_sandbox_electricity_purchase` verifies meter first |
| Purchase payload includes required fields | ☑ | | `serviceID`, `billersCode`, `variation_code`, `amount`, `phone`, `request_id` |
| Sandbox purchase via FulfillmentService | ☑ | PYL-SBOX-ELEC-* | Integration test |
| Status → `fulfilled` | ☑ | | **CERTIFIED in sandbox** |
| Meaningful VTPass response stored | ☑ | | `response_payload.fulfillment` |

### Token / unit response observations

Observed on successful sandbox prepaid electricity purchase (`test_sandbox_electricity_purchase`):

| Field path | Observed | Notes |
|------------|----------|-------|
| `content.transactions.unit_price` | ☑ | Unit price returned in transaction content |
| `token` | ☑ | Main recharge token |
| `tokenAmount` | ☑ | Token amount metadata |
| `resetToken` | ☑ | Reset token when provided by disco |
| `configureToken` | ☑ | Configure token when provided by disco |
| `units` | ☑ | Allocated units (kWh) |
| `tariff` | ☑ | Tariff reference |
| `costOfUnit` | ☑ | Cost per unit |
| `tariffBaseRate` | ☑ | Base tariff rate |

Exact nesting varies by disco; integration test logs matching paths without hardcoding assertions.

---

## Electricity (merchant verify)

| Check | Status | Reference | Notes |
|-------|--------|-----------|-------|
| Merchant verify before fulfill | ☑ | | Backend service ready |
| Purchase payload includes meter fields | ☑ | | Unit tests |
| Merchant verify in sandbox | ☑ | | **CERTIFIED in sandbox** |

---

## Failures

| Scenario | Status | Notes |
|----------|--------|-------|
| Invalid meter → failed | **SANDBOX-INCONCLUSIVE** | Sandbox may verify `00000000000` |
| Empty meter → rejected locally | ☑ | No VTPass API call |
| Invalid disco → failed | ☑ | **CERTIFIED in sandbox** |
| Unpaid transaction → rejected | ☑ | Ops fulfill guard (feature tests) |
| Timeout handled safely | ☑ | Feature test with Http fake |
| Data purchase generic failure | **PENDING** | Diagnostics added in PAY-015H |

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
- Electricity merchant verify and purchase work with configured sandbox test meter; token/unit fields returned on success.
- Data sandbox purchase still fails with generic `TRANSACTION FAILED`; integration test now prints sanitized diagnostics and supports an alternate variation code env var.
- `VTPassResponseMapper` prefers nested `content.error` details over generic failure descriptions when available.
- Invalid meter negative testing is unreliable in sandbox; local empty-meter validation covers malformed input.

---

## Known issues (at time of report)

1. Frontend checkout still uses mock meter verification UI — backend service ready but not wired to checkout.
2. Frontend `data_plan_id` values must be mapped to VTPass variation codes before production data launch.
3. Integration tests skip in CI unless `VTPASS_SANDBOX_TESTS=true`.
4. Data certification blocked until `test_sandbox_data_purchase` passes — use printed diagnostics to resolve variation code, phone, or sandbox balance issues.

---

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Engineering | | July 2026 | PARTIALLY CERTIFIED (sandbox) |
| Operations | | | |
| Founder | | | |

---

*Document: PAY-015 · VTPass Certification Report*
