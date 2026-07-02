# PAYLITY NG — Operations Runbook

For support staff and on-call operators. Keep this open during soft launch.

---

## Golden rules

1. **Never mark payment successful from the callback URL alone** — always check Laravel transaction status.
2. **Never fulfill unless status is `payment_success`.**
3. **Never enable `FEATURE_VTPASS_AUTO_FULFILL` without engineering approval.**
4. **Never share secret keys** (Paystack, VTPass) in chat or email.
5. **Always note the PAYLITY reference** (`PYL-YYYYMMDD-XXXXXX`) in every ticket.

---

## How to search by reference

### API

```bash
curl https://api.paylity.ng/api/v1/transactions/PYL-20260703-ABC123
```

### Web (customer-facing)

```
https://paylity.ng/transaction/PYL-20260703-ABC123
```

### Database (engineering only)

```sql
SELECT reference, status, product_type, product_amount, payable_amount,
       payment_provider, payment_reference, fulfillment_reference,
       failure_reason, created_at, updated_at
FROM transactions
WHERE reference = 'PYL-20260703-ABC123';
```

---

## Transaction statuses (what they mean)

| Status | Meaning | Customer sees |
|--------|---------|---------------|
| `payment_pending` | Paystack not confirmed yet | Payment pending |
| `payment_success` | Paid; not delivered yet | Awaiting delivery |
| `fulfillment_pending` | VTPass call in progress | Processing |
| `fulfilled` | Product delivered | Delivered |
| `payment_failed` | Paystack failed | Payment failed |
| `failed` | Fulfillment failed after payment | Delivery failed |

---

## Scenario: Money deducted but no delivery

**Symptoms:** Customer paid; airtime/data/electricity not received.

**Steps**

1. Get reference from customer (Paystack email or status page URL).
2. Fetch transaction via API.
3. Check status:
   - `payment_pending` → Run verify: `GET /api/v1/payments/paystack/verify/{reference}`
   - `payment_success` → Delivery not started → **manual fulfill** (see below)
   - `fulfillment_pending` → Wait 2 min; refresh status page
   - `failed` → Read `failure_reason`; escalate refund if payment_success was reached
   - `fulfilled` → Ask customer to wait 5–15 min; check wrong phone/meter number in payload
4. Verify `request_payload` (recipient phone, meter number, network).
5. If VTPass succeeded but customer denies receipt → escalate to engineering with VTPass `fulfillment_reference`.

**Do not:** Re-run checkout charge without refund investigation.

---

## Scenario: Payment pending

**Steps**

1. Ask customer to open callback link or status page.
2. Run verify endpoint manually.
3. If still pending after 15 minutes → check Paystack dashboard for transaction state.
4. If abandoned on Paystack → ask customer to retry checkout (new reference).

---

## Scenario: Payment failed

**Steps**

1. Confirm status `payment_failed` in API.
2. Read `failure_reason` if present.
3. Ask customer to retry with different card or bank.
4. No fulfillment action needed.

---

## Scenario: Fulfillment failed

**Symptoms:** Status `failed` after payment was successful.

**Steps**

1. Confirm prior status was `payment_success` (payment captured).
2. Read `failure_reason` from transaction.
3. Check VTPass sandbox/live dashboard if available.
4. **Do not** blindly retry fulfill without engineering review (risk double vend).
5. Escalate for **refund** if product cannot be delivered.

---

## Scenario: Duplicate transaction

**Symptoms:** Customer charged twice.

**Steps**

1. Collect both references.
2. Compare `customer_phone`, amount, timestamps.
3. If both `payment_success` + both fulfilled → escalate refund for duplicate.
4. If one still `payment_pending` → verify; may be abandoned duplicate.

---

## Refund escalation

PAYLITY MVP has **no automated refund API**.

**Escalate to engineering/finance with:**

- PAYLITY reference
- Paystack `payment_reference`
- Amount (`payable_amount`)
- Reason (failed fulfillment, duplicate, etc.)

Refunds are processed in **Paystack dashboard** manually until PAY-013+ tooling exists.

---

## Manual fulfillment (ops console only)

**When to use:** Transaction is `payment_success`, VTPass enabled, product not delivered.

**Requirements**

- `FEATURE_VTPASS=true`
- Valid VTPass credentials on server
- Operator access via internal console at `/ops` or ops API with `X-Operator-Key`

```bash
curl -X POST https://api.paylity.ng/api/v1/ops/transactions/PYL-20260703-ABC123/fulfill \
  -H "X-Operator-Key: YOUR_OPERATOR_ACCESS_KEY"
```

**Note:** The public `POST /api/v1/transactions/{reference}/fulfill` endpoint was removed in PAY-014.

**Expected progression:** `payment_success` → `fulfillment_pending` → `fulfilled` or `failed`

**If 503 `VTPASS_DISABLED`:** Feature flag off — contact engineering.

**If 422 `INVALID_TRANSACTION_STATUS`:** Payment not confirmed — run verify first.

---

## Re-verify payment

```bash
curl https://api.paylity.ng/api/v1/payments/paystack/verify/PYL-20260703-ABC123
```

Use when customer returned from Paystack but status stuck on pending.

---

## What NOT to do

| Action | Why |
|--------|-----|
| Edit transaction status in DB manually | Breaks audit trail |
| Enable auto-fulfill to "fix" backlog | Risk mass failed vends |
| Share fulfill endpoint publicly | Removed — use ops console only |
| Promise instant refund in chat | No automation yet |
| Trust customer screenshot as payment proof | Use Paystack verify |
| Fulfill when status is not `payment_success` | Unpaid delivery |

---

## Support WhatsApp script

> Hi, thanks for contacting PAYLITY NG. Please send your transaction reference (starts with PYL-) or the phone number used at checkout. We'll check payment and delivery status for you.

Configure real number: `NEXT_PUBLIC_WHATSAPP_URL` on web app.

---

## Escalation to engineering

Escalate when:

- Verify returns amount/reference mismatch errors
- Webhook signature failures spike in logs
- Multiple fulfillments fail with same VTPass error code
- Customer reports widespread outage
- Any suspected duplicate charge across many users

Include: reference, timestamps, status, `failure_reason`, screenshots (optional).

---

*Document: PAY-012 · Operations Runbook*
