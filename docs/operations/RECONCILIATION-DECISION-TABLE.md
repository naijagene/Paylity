# Reconciliation Decision Table

| Condition | Action | New VTPass purchase? |
|-----------|--------|----------------------|
| Confirmed provider failure | Schedule retry per policy | Yes (new request ID) |
| Uncertain / timeout | Requery original request ID | **No** |
| Provider requery success | Mark fulfilled locally | No |
| Provider requery failure | Mark `confirmed_failed`, schedule retry | Yes on retry only |
| Payment amount mismatch | Manual review | No |
| Paystack success + local payment_pending | Verify + repair payment | After payment confirmed |
| Paid + no fulfillment | Orchestrator fulfillment | Yes (first attempt) |
| Duplicate webhook | Idempotent verify | No extra purchase |
| Retry exhausted | Dead letter + manual review | No |
| Manual review open | Block automation | No |
| Fulfilled without ledger success | Escalate manual review | No |
