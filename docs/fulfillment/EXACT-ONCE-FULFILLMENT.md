# Exact-Once Fulfillment

## Service

`App\Services\Fulfillment\ExactOnceFulfillmentService` is the single entry point for all fulfillment triggers.

## Reservation Flow

1. Lock transaction row
2. Validate payment confirmed and not cancelled/manual-review
3. Block if uncertain/submitted attempt exists
4. Block if another active attempt exists
5. Create attempt with status `processing` and auditable `request_id`
6. Set transaction to `fulfillment_pending`
7. Call `FulfillmentService::executeAttempt()`

## Idempotent Outcomes

| Outcome | Meaning |
|---------|---------|
| `fulfilled` | Provider success; transaction fulfilled |
| `already_fulfilled` | Safe no-op |
| `active_attempt` | Uncertain or in-flight attempt must be reconciled first |
| `manual_review` | Automation paused |
| `payment_not_confirmed` | Payment not ready |
| `uncertain` | Provider outcome unknown; no resubmit |
| `failed` | Confirmed provider failure; retry policy applies |

## Attempt Ledger

Table: `fulfillment_attempts`

- One `succeeded` attempt per transaction (`successful_attempt_key` unique)
- One active `processing/submitted/uncertain` attempt per transaction (application guard + status index)
- Unique `request_id` globally

## Uncertain Result Policy

- Mark attempt `uncertain`
- Keep original `request_id`
- Do **not** submit a new VTPass purchase
- Run `paylity:reconcile-fulfillments` to requery provider
- Escalate to manual review after `fulfillment_uncertain_escalation_minutes`
