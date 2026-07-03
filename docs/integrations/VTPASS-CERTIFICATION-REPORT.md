# VTPass Certification Report — PAYLITY NG

**Document status:** Template — update after sandbox testing  
**Last updated:** July 2026  
**Ticket:** PAY-015

---

## Certification result

> **NOT CERTIFIED**

Update to **PARTIALLY CERTIFIED** or **CERTIFIED** only after completing sandbox test steps and recording evidence below.

| Result | Criteria |
|--------|----------|
| NOT CERTIFIED | No sandbox tests completed or critical failures |
| PARTIALLY CERTIFIED | Some products verified; blockers remain |
| CERTIFIED | Airtime, data, electricity, verify, and ops fulfill validated in sandbox |

---

## Environment

| Item | Value |
|------|-------|
| API environment | _local / staging_ |
| VTPass environment | Sandbox |
| Base URL | `https://sandbox.vtpass.com` |
| `FEATURE_VTPASS` | _true / false_ |
| `FEATURE_VTPASS_AUTO_FULFILL` | _false (required for launch)_ |
| Tester | _name_ |
| Test date | _YYYY-MM-DD_ |

---

## Credentials

| Check | Status | Notes |
|-------|--------|-------|
| Username configured | ☐ | |
| Password configured | ☐ | Not logged |
| API key configured | ☐ | Not logged |
| `paylity:vtpass-check` PASS | ☐ | |

---

## Connectivity

| Check | Status | Notes |
|-------|--------|-------|
| Base URL reachable | ☐ | |
| HTTP timeout configured | ☐ | Default 30s |
| Retry policy configured | ☐ | Default 2 retries |

---

## Authentication

| Check | Status | Notes |
|-------|--------|-------|
| Merchant verify accepted | ☐ | Test meter: _number_ |
| Invalid credentials rejected | ☐ | |

---

## Merchant Verify

| Check | Status | Notes |
|-------|--------|-------|
| Valid meter returns customer name | ☐ | |
| Meter number returned | ☐ | |
| Disco mapped correctly | ☐ | |
| Status mapped via `VTPassResponseMapper` | ☐ | |
| Unavailable when credentials missing | ☐ | Clear message shown |

---

## Airtime

| Check | Status | Reference | Notes |
|-------|--------|-----------|-------|
| Payload maps MTN → `mtn` | ☐ | | |
| Sandbox purchase via ops fulfill | ☐ | | |
| Status → `fulfilled` | ☐ | | |

---

## Data

| Check | Status | Reference | Notes |
|-------|--------|-----------|-------|
| Variation code sent | ☐ | | Confirm sandbox catalog match |
| Sandbox purchase via ops fulfill | ☐ | | |
| Status → `fulfilled` | ☐ | | |

---

## Electricity

| Check | Status | Reference | Notes |
|-------|--------|-----------|-------|
| Merchant verify before fulfill | ☐ | | Backend service ready |
| Purchase payload includes meter fields | ☐ | | |
| Sandbox purchase via ops fulfill | ☐ | | |
| Status → `fulfilled` | ☐ | | |

---

## Failures

| Scenario | Status | Notes |
|----------|--------|-------|
| Invalid meter → failed | ☐ | |
| Invalid disco → failed | ☐ | |
| Unpaid transaction → rejected | ☐ | Ops fulfill guard |
| Timeout handled safely | ☐ | Feature test with Http fake |

---

## Performance

| Metric | Target | Observed |
|--------|--------|----------|
| Merchant verify latency | < 10s | _ms_ |
| Purchase latency | < 30s | _ms_ |
| Retry on transient failure | Yes | _observed / not observed_ |

---

## Observations

_Document sandbox quirks, variation code mismatches, unsupported test numbers, or VTPass account limits._

- 
- 
- 

---

## Known issues (at time of report)

1. Frontend checkout still uses mock meter verification UI — backend `ElectricityMeterVerificationService` is ready but not wired to checkout (by design for PAY-015 scope).
2. Data plan IDs in frontend must be mapped to live VTPass variation codes before production.
3. Integration tests skip in CI unless `VTPASS_SANDBOX_TESTS=true`.

---

## Sign-off

| Role | Name | Date | Result |
|------|------|------|--------|
| Engineering | | | NOT CERTIFIED |
| Operations | | | |
| Founder | | | |

---

*Document: PAY-015 · VTPass Certification Report*
