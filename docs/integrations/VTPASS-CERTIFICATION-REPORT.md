# VTPass Certification Report — PAYLITY NG

**Document status:** Partial sandbox certification recorded  
**Last updated:** July 2026  
**Ticket:** PAY-015 / PAY-015C / PAY-015D / PAY-015E

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
| Electricity purchase | **PENDING valid test meter** | Set `VTPASS_TEST_ELECTRICITY_METER_NUMBER` and pass `test_sandbox_electricity_purchase` |
| Data purchase | **CERTIFIED in sandbox** | Set `VTPASS_TEST_DATA_VARIATION_CODE` and pass `test_sandbox_data_purchase` |
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
| Variation code sent | ☑ | | Valid sandbox code via `VTPASS_TEST_DATA_VARIATION_CODE` |
| Sandbox purchase via ops fulfill | ☑ | PYL-SBOX-DATA-* | Integration test |
| Status → `fulfilled` | ☑ | | **CERTIFIED in sandbox** when integration test passes |

---

## Electricity purchase

| Check | Status | Reference | Notes |
|-------|--------|-----------|-------|
| Merchant verify before purchase | ☑ | | `test_sandbox_electricity_purchase` verifies meter first |
| Purchase payload includes required fields | ☑ | | `serviceID`, `billersCode`, `variation_code`, `amount`, `phone`, `request_id` |
| Sandbox purchase via FulfillmentService | ☐ | PYL-SBOX-ELEC-* | **PENDING** — set `VTPASS_TEST_ELECTRICITY_METER_NUMBER` |
| Status → `fulfilled` | ☐ | | Do not certify until integration test passes |
| Meaningful VTPass response stored | ☐ | | `response_payload.fulfillment` |

**Blocker:** Set electricity purchase env values (see `VTPASS-SANDBOX-TEST-STEPS.md`) and run `test_sandbox_electricity_purchase`. The meter must verify with a customer name (not just HTTP 200). Electricity purchase stays **PENDING** until that test passes with `fulfilled` status.

### Token / unit response observations

Prepaid electricity purchases may return delivery details inside `response_payload.fulfillment.content` (exact field names vary by disco and sandbox vs live). Common patterns include recharge tokens, units (kWh), tariff references, or PIN-style values nested under `content` or transaction sub-objects.

The integration test logs any response paths matching token/unit/recharge-style keys without asserting exact field names. Record observed paths in this section after a successful sandbox purchase run.

| Field path (example) | Observed | Notes |
|----------------------|----------|-------|
| _Run integration test to populate_ | | |

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
- Electricity purchase requires env-driven test meter via `VTPASS_TEST_ELECTRICITY_*` — merchant verify alone does not certify purchase.
- Data sandbox fulfillment works when a valid VTPass variation code is configured.
- Invalid meter negative testing is unreliable in sandbox; local empty-meter validation covers malformed input.

---

## Known issues (at time of report)

1. Frontend checkout still uses mock meter verification UI — backend service ready but not wired to checkout.
2. Frontend `data_plan_id` values must be mapped to VTPass variation codes before production data launch.
3. Integration tests skip in CI unless `VTPASS_SANDBOX_TESTS=true`.
4. Electricity purchase certification blocked until `VTPASS_TEST_ELECTRICITY_METER_NUMBER` is set and integration test passes.

---

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Engineering | | July 2026 | PARTIALLY CERTIFIED (sandbox) |
| Operations | | | |
| Founder | | | |

---

*Document: PAY-015 · VTPass Certification Report*
