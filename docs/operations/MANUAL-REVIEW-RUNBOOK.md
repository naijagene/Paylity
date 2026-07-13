# Manual Review Runbook

## When automation stops

- `needs_manual_review = true`
- Retry processor skips transaction
- Orchestrator returns `manual_review` outcome
- No automatic VTPass purchase

## Common reasons

- Retry exhaustion
- Amount mismatch
- Prolonged uncertain provider outcome
- Ledger mismatch (fulfilled without succeeded attempt)

## Operator actions (Ops Console)

1. **View timeline** — `/transactions/{reference}`
2. **Reconcile payment** — verify Paystack state
3. **Reconcile fulfillment** — requery VTPass with original request ID
4. **Retry** — only after confirmed failure; passes through orchestrator
5. **Resume automation** — clears manual review flag
6. **Add note** — auditable operator context

## Never

- Force-deliver without provider confirmation
- Bypass orchestrator
- Submit new purchase while uncertain attempt exists
