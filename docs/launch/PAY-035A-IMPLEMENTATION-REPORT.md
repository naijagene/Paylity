# PAY-035A Implementation Report

## Exact root cause

Two issues combined to hide the Live Payment Certification section in production:

1. **Frontend hiding condition (primary code defect):** `LivePaymentCertificationSection` contained:

   ```tsx
   if (!certification) {
     return null;
   }
   ```

   When the Go-Live snapshot did not include `payment_certification` (or it was `null`), the entire panel returned `null` and never rendered.

2. **Production deployment mismatch (operational cause):** PAY-035 shipped in commit `b7bee934`, but production Ops likely ran an older build **or** production API had not yet returned `payment_certification` on `GET /api/v1/ops/go-live`. Either condition triggered the hiding guard above.

The section was also placed **after Quick Links** instead of between Launch Timeline and Provider Mode, which made it easy to miss even when rendered.

## Was the API snapshot missing the field?

- **Intended PAY-035 API:** yes, `OpsGoLiveService::snapshot()` includes `payment_certification`.
- **Production at time of report:** likely **missing or stale** on one side (API not migrated/deployed, or Ops UI deployed without the panel fix).
- **After PAY-035A:** field is **always present** with safe empty defaults, even when no certification runs exist or the certification table is unavailable.

## Did the frontend use a hiding condition?

**Yes.** `if (!certification) return null;` was the direct cause of the invisible panel.

## Was the production Ops deployment stale?

**Likely yes** for at least one of API or Ops. The PAY-035 commit exists locally (`b7bee934`) but production Go-Live matched the pre-PAY-035 section list ending at Quick Links.

## Files changed

| File | Change |
|------|--------|
| `apps/api/app/Services/Launch/PaymentCertificationService.php` | Expanded snapshot contract + `emptySnapshot()` |
| `apps/api/app/Services/Ops/OpsGoLiveService.php` | Safe fallback wrapper for certification snapshot |
| `apps/ops/src/lib/api/ops.ts` | Required `payment_certification` type + `resolvePaymentCertification()` |
| `apps/ops/src/components/go-live/GoLiveClient.tsx` | Always render panel; moved between Timeline and Provider Mode |
| `apps/ops/src/components/go-live/GoLiveClient.test.tsx` | Updated mock contract |
| `apps/ops/src/components/go-live/GoLiveClient.certification.test.tsx` | New certification rendering/action tests |
| `apps/api/tests/Feature/Api/V1/Pay033aLaunchReadinessFinalizationTest.php` | Snapshot contract assertions |
| `apps/api/tests/Feature/Pay035LivePaymentCutoverTest.php` | Snapshot + no-secrets assertions |

## Route contract

| Method | Path |
|--------|------|
| GET | `/api/v1/ops/go-live/payment-certification` |
| POST | `/api/v1/ops/go-live/payment-certification/preflight` |
| POST | `/api/v1/ops/go-live/payment-certification` |
| PATCH | `/api/v1/ops/go-live/payment-certification/{run}/reference` |
| POST | `/api/v1/ops/go-live/payment-certification/{run}/refresh` |
| POST | `/api/v1/ops/go-live/payment-certification/{run}/finalize` |
| GET | `/api/v1/ops/go-live/payment-certification/{run}/export` |

Also included on main snapshot:

| Method | Path |
|--------|------|
| GET | `/api/v1/ops/go-live` → always includes `payment_certification` |

## Deployment required

| Target | Required |
|--------|----------|
| API VPS | **Yes** — snapshot contract + safe defaults |
| Ops Vercel | **Yes** — panel always renders |

## Expected Git commit after fix

Suggested message:

```
fix(ops): restore live payment certification panel
```

Prior PAY-035 commit hash (already in repo):

```
b7bee9341e5ece2e59009fdd126d4f9b70b2d6f8
feat(payments): add live payment cutover and certification controls
```

Production should show the PAY-035A fix commit after deployment (run `git rev-parse HEAD` on the deployed branch).

## Test results

| Suite | Result |
|-------|--------|
| `php artisan test --filter=Pay035` | 21 passed |
| `php artisan test --filter=GoLive` | 5 passed |
| `php artisan test --filter=Pay033a` | 8 passed |
| Ops `npm run test` | 66 passed |
| Ops `npm run build` | Success |

1. Live Payment Certification heading visible on Go-Live Center
2. Panel visible with no certification session (empty state)
3. Degraded message when snapshot field absent
4. Run Live Payment Preflight action works
5. Create Certification Session requires confirmation
6. Existing Go-Live sections unchanged
7. No secrets in browser/API/export
8. Page no longer ends at Quick Links without certification section above Provider Mode
