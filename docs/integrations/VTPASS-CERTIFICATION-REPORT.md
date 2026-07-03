# VTPass Certification Report — PAYLITY NG

**Document status:** Partial sandbox certification recorded  
**Last updated:** July 2026  
**Ticket:** PAY-015 / PAY-015C–K

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
| Electricity merchant verify | **CERTIFIED in sandbox** | Uses `VTPASS_TEST_ELECTRICITY_*` (legacy `VTPASS_TEST_DISCO` / `VTPASS_TEST_METER_NUMBER` fallback); same normalized disco mapping as purchase via `ElectricityDiscoMapper` |
| Electricity purchase | **CERTIFIED in sandbox** | `test_sandbox_electricity_purchase` passes with token/unit fields logged |
| Data purchase | **PENDING** | VTPass code `016` / `TRANSACTION FAILED` — not certified; see observed payload below |
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
| `paylity:vtpass-check` PASS | ☑ | Merchant verify passes with `VTPASS_TEST_ELECTRICITY_*` test meter |

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
| Merchant verify accepted | ☑ | Test meter configured via `VTPASS_TEST_ELECTRICITY_*` (legacy fallback supported) |
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

**Latest observed failure (July 2026 sandbox run):**

| Field | Value |
|-------|-------|
| `serviceID` | `mtn-data` |
| `variation_code` | `mtn-10mb-100` |
| `billersCode` | recipient MSISDN (see `VTPASS_TEST_DATA_BILLERS_CODE` / `VTPASS_TEST_DATA_PHONE`) |
| `phone` | recipient/contact MSISDN |
| `code` | `016` |
| `status` | `failed` |
| `response_description` | `TRANSACTION FAILED` |

**Payload audit (PAY-015L):** Outgoing purchase payload matches official VTPass MTN Data docs (`request_id`, `serviceID`, `billersCode`, `variation_code`, `amount`, `phone`). Sanitized outgoing payload is logged during sandbox tests and printed in integration failure diagnostics.

**Conclusion:** Payload structure is valid and matches official docs. Sandbox docs require `08011111111` for success; other phones simulate failure. If failure persists with the success phone and documented variation code, escalate to VTPass support (sandbox wallet, account rules, or product availability). **Do not mark Data as certified** until `test_sandbox_data_purchase` passes.

Set `VTPASS_SKIP_DATA_CERTIFICATION=true` to run Airtime + Electricity integration certification without failing the suite on Data. Integration test still prints sanitized diagnostics when Data is not skipped.

**Diagnostics:** Run `test_sandbox_data_purchase` with sandbox flags enabled to print sanitized `response_payload`, nested `content` errors, `request_id`, and phone. Optional `VTPASS_TEST_DATA_VARIATION_CODE_ALT` may be tried when not skipping Data certification.

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
| Merchant verify in sandbox | ☑ | **CERTIFIED in sandbox** — `test_sandbox_electricity_merchant_verify` uses same `VTPASS_TEST_ELECTRICITY_*` config and `ElectricityDiscoMapper` as purchase pre-verify |

---

## Failures

| Scenario | Status | Notes |
|----------|--------|-------|
| Invalid meter → failed | **SANDBOX-INCONCLUSIVE** | Sandbox may verify `00000000000` |
| Empty meter → rejected locally | ☑ | No VTPass API call |
| Invalid disco → failed | ☑ | **CERTIFIED in sandbox** |
| Unpaid transaction → rejected | ☑ | Ops fulfill guard (feature tests) |
| Timeout handled safely | ☑ | Feature test with Http fake |
| Data purchase generic failure | **PENDING** | Code `016` / `TRANSACTION FAILED` for `mtn-data` + `mtn-10mb-100`; skip with `VTPASS_SKIP_DATA_CERTIFICATION=true` |

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
- Data sandbox purchase fails with VTPass code `016` / `TRANSACTION FAILED` for `serviceID=mtn-data`, `variation_code=mtn-10mb-100`; integration suite can skip Data via `VTPASS_SKIP_DATA_CERTIFICATION=true` while Airtime and Electricity certify cleanly.
- Merchant verify and electricity purchase share `VTPASS_TEST_ELECTRICITY_*` config and `ElectricityDiscoMapper` normalization (legacy `VTPASS_TEST_DISCO` / `VTPASS_TEST_METER_NUMBER` fallback only when electricity-specific values are unset).
- `VTPassResponseMapper` prefers nested `content.error` details over generic failure descriptions when available.
- Invalid meter negative testing is unreliable in sandbox; local empty-meter validation covers malformed input.

---

## Known issues (at time of report)

1. Frontend checkout still uses mock meter verification UI — backend service ready but not wired to checkout.
2. Frontend `data_plan_id` values must be mapped to VTPass variation codes before production data launch.
3. Integration tests skip in CI unless `VTPASS_SANDBOX_TESTS=true`.
4. Data certification blocked until VTPass sandbox accepts a data variation — observed failure is code `016` for `mtn-data` / `mtn-10mb-100`. Use `VTPASS_SKIP_DATA_CERTIFICATION=true` to certify Airtime + Electricity without failing the integration suite on Data.

---

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Engineering | | July 2026 | PARTIALLY CERTIFIED (sandbox) |
| Operations | | | |
| Founder | | | |

---

*Document: PAY-015 · VTPass Certification Report*
