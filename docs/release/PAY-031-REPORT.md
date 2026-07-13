# PAY-031 Implementation Report

## 1. Root-cause and race-condition audit

| Risk | Root cause | Mitigation |
|------|------------|------------|
| Double fulfillment | Webhook, callback, retry, reconciliation, and ops all called `FulfillmentService::fulfill()` directly | Central `ExactOnceFulfillmentService` with row lock |
| Blind resubmit after timeout | No uncertain state; retries issued new VTPass pay | `uncertain` attempt status blocks new purchase; requery via `paylity:reconcile-fulfillments` |
| Lost provider request | Attempt recorded after VTPass response | Attempt persisted in `processing` before HTTP submit |
| Concurrent triggers | No `lockForUpdate` | DB transaction + `lockForUpdate` + active attempt check |
| Orphaned Paystack success | Stale `payment_pending` | Strengthened `paylity:reconcile-payments` |
| Fulfilled without ledger | No invariant check | Reconciliation escalates missing succeeded attempt |

## 2. Existing risks found

- Direct VTPass calls from multiple code paths
- Checkout `request_id` reused across retries
- `VTPassService::queryTransaction()` unused
- No `withoutOverlapping()` on scheduler jobs
- No ops reconciliation action API
- Attempt ledger lacked trigger source, lifecycle status, uniqueness

## 3. Architecture implemented

```
Triggers → ExactOnceFulfillmentService (lock + reserve)
         → FulfillmentService::executeAttempt (single VTPass pay)
         → FulfillmentAttemptRecorder (pre/post state)
         
Reconciliation:
  paylity:reconcile-payments → PaymentReconciliationService
  paylity:reconcile-fulfillments → VtpassFulfillmentReconciliationService (requery only)
  
Ops: GET/POST /api/v1/ops/reconciliation/*
```

## 4. Exact-once strategy

1. Lock transaction row
2. Refuse if fulfilled, cancelled, manual review, or blocking uncertain/submitted attempt
3. Create attempt with unique `request_id` before provider call
4. Return idempotent result for already-fulfilled
5. DB unique on `request_id` and `successful_attempt_key`

## 5. Provider request-ID format

`{PAYLITY_REFERENCE}-F{nn}` — e.g. `PYL-20260710-ABC123-F01`

## 6. Database migrations

- `2026_07_10_000001_extend_fulfillment_attempts_for_exact_once.php`
  - Adds trigger_source, status, provider fields, timestamps, error fields
  - Unique indexes on `request_id`, `successful_attempt_key`
  - Index on `(transaction_id, status)`

## 7. Files created

| File |
|------|
| `apps/api/app/Enums/FulfillmentAttemptStatus.php` |
| `apps/api/app/Enums/FulfillmentTriggerSource.php` |
| `apps/api/app/Support/Fulfillment/FulfillmentOrchestrationResult.php` |
| `apps/api/app/Services/Fulfillment/ExactOnceFulfillmentService.php` |
| `apps/api/app/Services/Fulfillment/VtpassFulfillmentReconciliationService.php` |
| `apps/api/app/Services/Ops/OpsReconciliationService.php` |
| `apps/api/app/Http/Controllers/Api/V1/Ops/OpsReconciliationController.php` |
| `apps/api/app/Console/Commands/PaylityReconcileFulfillmentsCommand.php` |
| `apps/api/tests/Feature/Api/V1/Pay031ExactOnceFulfillmentTest.php` |
| `apps/ops/src/components/reconciliation/ReconciliationClient.tsx` |
| `apps/ops/src/app/reconciliation/page.tsx` |
| `docs/payments/TRANSACTION-LIFECYCLE.md` |
| `docs/payments/RECONCILIATION-RUNBOOK.md` |
| `docs/fulfillment/EXACT-ONCE-FULFILLMENT.md` |
| `docs/fulfillment/VTPASS-REQUEST-ID-POLICY.md` |
| `docs/operations/MANUAL-REVIEW-RUNBOOK.md` |
| `docs/operations/RECONCILIATION-DECISION-TABLE.md` |
| `docs/deployment/SCHEDULER-OPERATIONS.md` |

## 8. Files modified

| File | Change |
|------|--------|
| `FulfillmentService.php` | `executeAttempt()`; direct fulfill deprecated |
| `FulfillmentAttemptRecorder.php` | Pre-submit lifecycle |
| `VTPassRequestIdGenerator.php` | Per-attempt ID format |
| `FulfillmentAttempt.php` | New fillable/casts |
| `PaymentVerificationService.php` | Orchestrator for auto-fulfill |
| `FulfillmentRetryService.php` | Orchestrator for retries |
| `PaymentReconciliationService.php` | Options, orchestrator, ledger audit |
| `OpsTransactionController.php` | Orchestrator for ops actions |
| `TransactionEventService.php` | PAY-031 event types |
| `SystemSettingKeys.php` | Reconciliation settings |
| `PlatformSettingsSeeder.php` | Staging defaults |
| `bootstrap/app.php` | Scheduler overlap protection |
| `routes/api.php` | Reconciliation endpoints |
| `apps/ops/src/lib/api/ops.ts` | Reconciliation API client |
| `apps/ops/src/components/layout/OpsShell.tsx` | Nav link |
| Test updates for new error codes / request IDs |

## 9. Scheduler changes

- Added `paylity:reconcile-fulfillments` every 10 minutes
- `withoutOverlapping()` + `onOneServer()` on reconcile and retry commands

## 10. Ops Console changes

- New **Reconciliation** page with summary cards and exception queues
- Safe actions: reconcile payment/fulfillment, retry, resume automation
- No force-deliver button

## 11. Tests and results

| Suite | Result |
|-------|--------|
| `Pay031ExactOnceFulfillmentTest` | 9 passed |
| `VTPassFulfillmentTest` | 18 passed |
| `PaystackReliabilityTest` | 7 passed |
| `OpsConsoleTest` | 7 passed |
| Ops vitest | 33 passed |

## 12. Exact API files to upload manually

Upload entire `apps/api` directory or at minimum:

- `app/Services/Fulfillment/ExactOnceFulfillmentService.php`
- `app/Services/Fulfillment/VtpassFulfillmentReconciliationService.php`
- `app/Services/Fulfillment/FulfillmentService.php`
- `app/Services/Fulfillment/FulfillmentAttemptRecorder.php`
- `app/Services/Payments/PaymentReconciliationService.php`
- `app/Services/Ops/OpsReconciliationService.php`
- `app/Http/Controllers/Api/V1/Ops/OpsReconciliationController.php`
- `app/Console/Commands/PaylityReconcileFulfillmentsCommand.php`
- `app/Console/Commands/PaylityReconcilePaymentsCommand.php`
- `database/migrations/2026_07_10_000001_extend_fulfillment_attempts_for_exact_once.php`
- `bootstrap/app.php`
- `routes/api.php`

## 13. Exact deployment commands

```bash
cd apps/api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan db:seed --class=PlatformSettingsSeeder  # if settings missing
```

Ops frontend:

```bash
cd apps/ops
npm ci
npm run build
```

## 14. Smoke-test checklist

- [ ] Checkout still initializes and redirects to Paystack
- [ ] Webhook + callback both reach `payment_success` without downgrade
- [ ] Duplicate webhook is idempotent
- [ ] Single VTPass pay per paid transaction
- [ ] Uncertain attempt blocks second pay
- [ ] `paylity:reconcile-payments --dry-run` makes no changes
- [ ] `paylity:reconcile-fulfillments` repairs provider success
- [ ] Ops `/reconciliation` loads with operator key
- [ ] Operator retry cannot double-fulfill
- [ ] Scheduler `schedule:list` shows overlap-protected jobs

## 15. Rollback procedure

1. Revert API deploy to prior release tag
2. `php artisan migrate:rollback --step=1` (only if migration causes issues; safe to leave columns)
3. Remove `paylity:reconcile-fulfillments` from schedule if needed
4. Re-enable prior fulfillment path only if hotfix required (not recommended)
5. Monitor `fulfillment_attempts` for stuck `processing` rows

## Suggested commit

```
feat(reliability): enforce exact-once fulfillment and reconciliation
```
