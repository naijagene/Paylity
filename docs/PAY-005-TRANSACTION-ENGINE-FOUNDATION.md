# PAY-005 — Transaction Engine Foundation

**Product:** PAYLITY NG  
**Milestone:** PAY-005 (Documentation)  
**Next implementation:** PAY-006 (Laravel API scaffold)  
**Status:** Ready for backend build

---

## 1. Transaction Engine Goal

PAYLITY NG must treat every utility purchase as **one transaction lifecycle** — not three separate product systems.

Airtime, Data, and Electricity are **product types** handled by the same engine. The frontend already uses a Universal Checkout Engine (PAY-004). The backend must mirror that design:

- One entry point for checkout initialization.
- One `transactions` table.
- One status lifecycle.
- One payment flow.
- One fulfillment pipeline.
- Product-specific logic isolated in **adapters**, not duplicated across controllers.

**Do not build:**
- Separate `AirtimeController`, `DataController`, `ElectricityController` as primary architecture.
- Wallet, registration, dashboard, or betting flows in v1.

**Do build:**
- A single transaction engine that accepts `product_type` and routes work to the correct adapter.

```
Customer checkout → Transaction created → Fraud checks → Payment → Fulfillment → Receipt → Complete
```

---

## 2. Core Backend Architecture

Laravel API modules and responsibilities:

| Module | Responsibility |
|--------|----------------|
| **CheckoutController** | HTTP entry point. Accepts checkout payload, returns transaction reference and payment-ready response. No product-specific business logic. |
| **TransactionService** | Creates and updates transactions. Owns status transitions. Generates PAYLITY references. Single source of truth for transaction state. |
| **FraudService** | Pre-payment security checks on **product amount** (`amount`): guest cap, daily phone limit, daily IP limit. Blocks payment initialization when rules fail. |
| **FeeService** | Calculates PAYLITY convenience fee, gateway charge (when applicable), and final payable `total_amount`. Backend is authoritative — frontend display is indicative until initialize response returns. |
| **PaymentService** | Initializes and confirms payments. Paystack integration lands here later. v1 returns placeholder payment state. |
| **FulfillmentService** | Runs after `payment_success`. Dispatches to correct product adapter. VTPass integration lands here later. |
| **ReceiptService** | Builds receipt payload for frontend display, email/SMS later. |

### Request flow (high level)

```
CheckoutController
  → TransactionService (create)
  → ProductAdapter (validate payload)
  → FeeService (calculate)
  → FraudService (check)
  → TransactionService (status: validated)
  → PaymentService (initialize — placeholder in PAY-006)
  → Response
```

### Directory suggestion (PAY-006)

```
app/
  Http/Controllers/Api/CheckoutController.php
  Http/Controllers/Api/TransactionController.php
  Http/Controllers/Api/PaystackCallbackController.php
  Services/TransactionService.php
  Services/FraudService.php
  Services/FeeService.php
  Services/PaymentService.php
  Services/FulfillmentService.php
  Services/ReceiptService.php
  Products/Contracts/ProductAdapterInterface.php
  Products/Adapters/AirtimeAdapter.php
  Products/Adapters/DataAdapter.php
  Products/Adapters/ElectricityAdapter.php
  Models/Transaction.php
```

---

## 3. Product Adapter Pattern

Each product implements a shared contract. The transaction engine calls the adapter — never the reverse.

### Interface (conceptual)

```php
interface ProductAdapterInterface
{
    public function productType(): string;

    public function validate(array $payload): void;

    public function normalizePayload(array $payload): array;

    public function resolveAmount(array $payload): int;

    public function fulfill(Transaction $transaction): FulfillmentResult;
}
```

### v1 adapters

| Adapter | Validates | Amount source | Fulfillment (later) |
|---------|-----------|---------------|---------------------|
| **AirtimeAdapter** | network, recipient_phone, amount | User-selected amount | VTPass airtime API |
| **DataAdapter** | network, recipient_phone, data_plan_id | Plan price from catalog | VTPass data API |
| **ElectricityAdapter** | disco, meter_type, meter_number, customer_name, amount | User-selected amount | VTPass electricity API |

### Adapter registry

```php
ProductAdapterRegistry::resolve('airtime') → AirtimeAdapter
ProductAdapterRegistry::resolve('data')    → DataAdapter
ProductAdapterRegistry::resolve('electricity') → ElectricityAdapter
```

Invalid `product_type` → `422` validation error before transaction creation.

### Future products (same pattern)

| Product key | Adapter (future) | Notes |
|-------------|------------------|-------|
| `tv` | TvAdapter | DSTV, GOtv, Startimes |
| `internet` | InternetAdapter | ISP account + plan |
| `education` | EducationAdapter | Institution + student ID |
| `betting` | BettingAdapter | Compliance gate required — do not expose until approved |

Adding a product = new adapter + registry entry + optional catalog config. **No new checkout controller.**

---

## 4. Transaction Reference Format

PAYLITY owns the customer-facing reference. Paystack uses the same value as `reference` metadata.

### Format

```
PYL-YYYYMMDD-XXXXXX
```

| Segment | Rule |
|---------|------|
| Prefix | Always `PYL` |
| Date | UTC or `Africa/Lagos` date at generation time, `YYYYMMDD` |
| Suffix | 6-character uppercase alphanumeric, cryptographically random |

### Example

```
PYL-20260702-HQ8R91
```

### Rules

1. Generate reference in `TransactionService` **before** any Paystack initialization.
2. Pass the same reference to Paystack as transaction reference.
3. Store in `transactions.reference` (unique index).
4. Display on receipts, success pages, and support lookups.
5. Never reuse a reference, even if payment fails.
6. Suffix charset: `A-Z` and `2-9` (exclude `0`, `O`, `I`, `1` to reduce confusion).

### Generation pseudocode

```php
$date = now('Africa/Lagos')->format('Ymd');
$suffix = strtoupper(Str::random(6, 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'));
$reference = "PYL-{$date}-{$suffix}";
```

---

## 5. Transaction Statuses

Use **only** these statuses. No ad-hoc strings.

| Status | When used |
|--------|-----------|
| `created` | Transaction row inserted after basic request parsing. Short-lived. |
| `validated` | Product payload, fee, and fraud checks passed. Ready for payment init. |
| `payment_pending` | Payment initialized (Paystack authorization URL issued or placeholder set). Awaiting customer payment. |
| `payment_success` | Payment confirmed via callback/webhook. Ready for fulfillment. |
| `payment_failed` | Payment declined, abandoned, or verification failed. Terminal unless retried as new transaction. |
| `fulfillment_pending` | Fulfillment dispatched to adapter / VTPass. Awaiting provider response. |
| `fulfilled` | Product delivered successfully. Terminal success state. |
| `failed` | Fulfillment failed after successful payment. Requires support/refund process later. |
| `cancelled` | Transaction cancelled before payment completion (timeout, user abort, admin action later). |

### Status flow

```
created
  → validated
  → payment_pending
      → payment_success → fulfillment_pending → fulfilled
      → payment_failed (terminal)
      → cancelled (terminal)
  → failed (fulfillment failure after payment_success)
```

### Transition rules

- Only `TransactionService` may change status (not controllers directly).
- Log every transition in `response_payload` or a future `transaction_events` table.
- Frontend polls `GET /api/transactions/{reference}` during payment/fulfillment.

---

## 6. Transaction Data Model

### Table: `transactions`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | Auto-increment |
| `reference` | string(32) unique | `PYL-YYYYMMDD-XXXXXX` |
| `product_type` | string(32) | `airtime`, `data`, `electricity` |
| `customer_phone` | string(20) | Normalized Nigerian MSISDN |
| `customer_email` | string nullable | Optional receipt email |
| `customer_name` | string nullable | Electricity / verified name |
| `amount` | unsigned integer | Product/customer amount in kobo (subject to guest and fraud limits) |
| `fee_amount` | unsigned integer | PAYLITY convenience fee in kobo (₦100 in v1) |
| `gateway_charge_amount` | unsigned integer default 0 | Payment gateway charge passed to customer in kobo |
| `total_amount` | unsigned integer | Final payable: `amount + fee_amount + gateway_charge_amount` in kobo |
| `currency` | string(3) default `NGN` | |
| `status` | string(32) | See §5 |
| `payment_provider` | string nullable | e.g. `paystack` |
| `payment_reference` | string nullable | Provider payment ref |
| `payment_authorization_url` | text nullable | Paystack checkout URL |
| `fulfillment_provider` | string nullable | e.g. `vtpass` |
| `fulfillment_reference` | string nullable | Provider fulfillment ref |
| `request_payload` | json | Normalized product + customer payload |
| `response_payload` | json nullable | Payment/fulfillment responses, status history |
| `failure_reason` | string nullable | Human-readable failure |
| `ip_address` | string nullable | Request IP for fraud rules |
| `user_agent` | text nullable | Request UA |
| `verified_phone` | boolean default false | OTP verified (future) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Indexes

- `unique(reference)`
- `index(status)`
- `index(customer_phone, created_at)`
- `index(ip_address, created_at)`
- `index(product_type, created_at)`

### Money storage

Store all monetary values in **kobo** (integer) in the database. Convert to naira only in API responses and receipts.

Example: ₦1,000 airtime + ₦100 convenience fee + ₦50 gateway charge → `amount = 100000`, `fee_amount = 10000`, `gateway_charge_amount = 5000`, `total_amount = 115000`.

Max guest product example: ₦10,000 airtime + ₦100 convenience fee + gateway charge → `amount = 1000000`, `fee_amount = 10000`, `gateway_charge_amount = {varies}`, `total_amount > 1010000` — **allowed** for guest checkout.

### `request_payload` structure (normalized)

```json
{
  "product_type": "airtime",
  "customer_phone": "08012345678",
  "customer_email": "user@example.com",
  "network": "MTN",
  "recipient_phone": "08098765432",
  "amount": 100000,
  "metadata": {}
}
```

Product-specific keys live inside this JSON. Adapters define required keys.

---

## 7. Fee Rules & Money Policy

### PAYLITY money model (v1)

Three separate line items. Do not merge them for limit checks.

| Line item | Field | v1 rule |
|-----------|-------|---------|
| Product amount | `amount` | What the customer buys (airtime, data plan, electricity units) |
| PAYLITY convenience fee | `fee_amount` | Flat ₦100 per product |
| Gateway charge | `gateway_charge_amount` | Passed to customer separately where supported (Paystack later) |

**Final payable total:**

```
total_amount = amount + fee_amount + gateway_charge_amount
```

The customer pays `total_amount`. Fraud and guest limits apply to **`amount` only**, not fees or gateway charges.

### v1 convenience fees (per product)

| Product | PAYLITY convenience fee |
|---------|-------------------------|
| Airtime | ₦100 |
| Data | ₦100 |
| Electricity | ₦100 |

`FeeService` calculation:

```
fee_amount = 10000 kobo (₦100)
gateway_charge_amount = calculated by PaymentService when Paystack is live; 0 in PAY-006 stub
total_amount = amount + fee_amount + gateway_charge_amount
```

### Gateway charges

- Passed to the customer as a separate line item where the payment provider supports it.
- Stored in `gateway_charge_amount`.
- Not included in guest or daily fraud limit calculations.
- PAY-006: set `gateway_charge_amount = 0` (placeholder until Paystack pricing is wired).

### Guest product amount cap

For unverified guests (`verified_phone = false`):

```
amount must not exceed 1_000_000 kobo (₦10,000)
```

Fees and gateway charges are **excluded** from this cap. A guest may pay more than ₦10,000 in total when fees apply.

**Allowed guest example:**

```
₦10,000 airtime
+ ₦100 PAYLITY convenience fee
+ ₦50 gateway charge (example)
= ₦10,150 payable total ✓
```

If product amount exceeds ₦10,000 → fraud check fails → no payment initialization.

### Minimum product amount

| Rule | Value |
|------|-------|
| Minimum `amount` | ₦50 (5000 kobo) unless data plan price overrides |

Backend validates independently of frontend.

---

## 8. Fraud / Security Rules

All checks run in `FraudService` **before** payment initialization.

| Rule | v1 behavior | Error code |
|------|-------------|------------|
| Guest product cap | Block if `amount > ₦10,000` and `verified_phone = false` | `GUEST_LIMIT_EXCEEDED` |
| Daily phone limit | Block if sum of `amount` for `customer_phone` in last 24h > ₦20,000 | `PHONE_DAILY_LIMIT_EXCEEDED` |
| Daily IP limit | Block if sum of `amount` for `ip_address` in last 24h > ₦30,000 | `IP_DAILY_LIMIT_EXCEEDED` |
| OTP for higher amounts | Not built in PAY-006. Reserve `verified_phone` flag for product amounts above ₦10,000. | `OTP_REQUIRED` (future) |
| Payment init gate | `PaymentService::initialize()` called only after all fraud checks pass | — |

**Not limited by fraud rules (v1):** `fee_amount`, `gateway_charge_amount`, `total_amount`.

### Failed fraud response

- HTTP `422 Unprocessable Entity`
- Transaction status stays `created` or moves to `cancelled` with `failure_reason`
- Do **not** call Paystack

### Daily limit calculation

Count transactions where:
- `customer_phone` or `ip_address` matches
- `created_at >= now() - 24 hours`
- `status` in (`payment_pending`, `payment_success`, `fulfillment_pending`, `fulfilled`)

Use **`amount`** (product amount) sum only — exclude `fee_amount` and `gateway_charge_amount`.

---

## 9. API Endpoints

Base path: `/api`

All responses use JSON. Money in responses shown in **kobo** and **naira** for frontend convenience.

---

### POST `/api/checkout/initialize`

**Purpose:** Validate checkout, create transaction, apply fees and fraud checks, return payment-ready response.

**Request body:**

```json
{
  "product_type": "airtime",
  "customer_phone": "08012345678",
  "customer_email": "user@example.com",
  "payload": {
    "network": "MTN",
    "recipient_phone": "08098765432",
    "amount": 1000
  }
}
```

**Product payload variants:**

```json
// data
{
  "product_type": "data",
  "customer_phone": "08012345678",
  "payload": {
    "network": "MTN",
    "recipient_phone": "08098765432",
    "data_plan_id": "mtn-1gb-daily"
  }
}

// electricity
{
  "product_type": "electricity",
  "customer_phone": "08012345678",
  "payload": {
    "disco": "IKEDC",
    "meter_type": "prepaid",
    "meter_number": "12345678901",
    "customer_name": "John Doe",
    "amount": 5000
  }
}
```

**Success response `201` (standard example):**

```json
{
  "success": true,
  "data": {
    "reference": "PYL-20260702-HQ8R91",
    "status": "payment_pending",
    "product_type": "airtime",
    "amount": 100000,
    "fee_amount": 10000,
    "gateway_charge_amount": 0,
    "total_amount": 110000,
    "amount_naira": 1000,
    "fee_amount_naira": 100,
    "gateway_charge_amount_naira": 0,
    "total_amount_naira": 1100,
    "currency": "NGN",
    "payment": {
      "provider": "paystack",
      "status": "placeholder",
      "authorization_url": null,
      "message": "Payment integration coming next"
    }
  }
}
```

**Success response `201` (max guest product amount — allowed):**

```json
{
  "success": true,
  "data": {
    "reference": "PYL-20260702-KM4T82",
    "status": "payment_pending",
    "product_type": "airtime",
    "amount": 1000000,
    "fee_amount": 10000,
    "gateway_charge_amount": 5000,
    "total_amount": 1015000,
    "amount_naira": 10000,
    "fee_amount_naira": 100,
    "gateway_charge_amount_naira": 50,
    "total_amount_naira": 10150,
    "currency": "NGN",
    "payment": {
      "provider": "paystack",
      "status": "placeholder",
      "authorization_url": null,
      "message": "Payment integration coming next"
    }
  }
}
```

Note: `amount_naira` is ₦10,000 (within guest cap). `total_amount_naira` is ₦10,150 — this is valid.

**Error response `422`:**

```json
{
  "success": false,
  "error": {
    "code": "GUEST_LIMIT_EXCEEDED",
    "message": "Guest product amount is limited to ₦10,000"
  }
}
```

**Error response `400`:**

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "fields": {
      "payload.recipient_phone": ["Enter a valid Nigerian phone number"]
    }
  }
}
```

---

### GET `/api/transactions/{reference}`

**Purpose:** Poll transaction status for payment, fulfillment, and receipt display.

**Success response `200`:**

```json
{
  "success": true,
  "data": {
    "reference": "PYL-20260702-HQ8R91",
    "status": "fulfilled",
    "product_type": "airtime",
    "customer_phone": "08012345678",
    "amount_naira": 1000,
    "fee_amount_naira": 100,
    "gateway_charge_amount_naira": 0,
    "total_amount_naira": 1100,
    "currency": "NGN",
    "failure_reason": null,
    "payment_reference": null,
    "fulfillment_reference": null,
    "created_at": "2026-07-02T21:30:00+01:00",
    "updated_at": "2026-07-02T21:31:00+01:00",
    "receipt": {
      "brand": "PAYLITY NG",
      "reference": "PYL-20260702-HQ8R91",
      "product_type": "airtime",
      "customer_phone": "08012345678",
      "amount_naira": 1000,
      "fee_amount_naira": 100,
      "gateway_charge_amount_naira": 0,
      "total_amount_naira": 1100,
      "status": "fulfilled",
      "timestamp": "2026-07-02T21:31:00+01:00",
      "fulfillment_reference": null
    }
  }
}
```

**Error response `404`:**

```json
{
  "success": false,
  "error": {
    "code": "TRANSACTION_NOT_FOUND",
    "message": "Transaction not found"
  }
}
```

---

### POST `/api/payments/paystack/callback`

**Purpose:** Browser redirect callback after Paystack checkout (future). Updates transaction payment state.

**Request body (future Paystack shape — stub in PAY-006):**

```json
{
  "reference": "PYL-20260702-HQ8R91",
  "trxref": "PYL-20260702-HQ8R91"
}
```

**Success response `200` (PAY-006 stub):**

```json
{
  "success": true,
  "data": {
    "reference": "PYL-20260702-HQ8R91",
    "status": "payment_success",
    "message": "Paystack callback handler placeholder"
  }
}
```

---

### POST `/api/payments/paystack/webhook`

**Purpose:** Server-to-server Paystack event handler (future). Verifies signature, idempotent status update.

**Request body:** Raw Paystack event payload (stub in PAY-006).

**Success response `200`:**

```json
{
  "success": true,
  "message": "Webhook received"
}
```

**Error response `401`:**

```json
{
  "success": false,
  "error": {
    "code": "INVALID_SIGNATURE",
    "message": "Invalid Paystack webhook signature"
  }
}
```

---

## 10. Initialize Checkout Flow

Step-by-step (PAY-006 implements this logic):

```
1. CheckoutController receives POST /api/checkout/initialize
2. Validate top-level fields: product_type, customer_phone, payload
3. ProductAdapterRegistry resolves adapter for product_type
4. Adapter validates + normalizes payload
5. Adapter resolves amount (kobo)
6. FeeService calculates fee_amount (₦100 convenience fee)
7. TransactionService creates row:
     - reference = PYL-YYYYMMDD-XXXXXX
     - status = created
     - store request_payload, ip_address, user_agent
8. FraudService runs all v1 rules against amount (product amount), phone, IP
     - on fail → set failure_reason, return 422, do not proceed
9. TransactionService updates status → validated
10. PaymentService.initialize(transaction)
     - PAY-006: placeholder only, no live Paystack call
     - set gateway_charge_amount (0 in stub; Paystack-calculated later)
     - set total_amount = amount + fee_amount + gateway_charge_amount
     - set status → payment_pending
     - payment.provider = paystack, authorization_url = null
11. Return 201 with reference, line items, and payment placeholder
```

### Important rules

- Reference exists before step 10.
- Frontend stores `reference` and polls GET transaction endpoint after real payment integration.
- Duplicate initialize requests create **new** transactions (no idempotent retry in v1).

---

## 11. Fulfillment Flow

Fulfillment runs **only after** `payment_success`.

```
1. Paystack callback/webhook confirms payment
2. TransactionService → status: payment_success
3. FulfillmentService.fulfill(transaction)
4. TransactionService → status: fulfillment_pending
5. Adapter.fulfill(transaction)
     - PAY-006: mock success/fail stub
     - PAY-007+: VTPass API call
6. On provider success:
     - store fulfillment_reference
     - status → fulfilled
7. On provider failure:
     - store failure_reason
     - status → failed
8. ReceiptService builds receipt payload
```

### Fulfillment never runs when

- Status is `payment_pending`, `payment_failed`, or `cancelled`
- Fraud checks have not passed
- Payment has not reached `payment_success`

---

## 12. Receipt Rules

`ReceiptService` returns a consistent payload for frontend, email, and SMS (later).

### Required fields

| Field | Source |
|-------|--------|
| Brand | `"PAYLITY NG"` |
| Transaction reference | `transactions.reference` |
| Product type | `transactions.product_type` |
| Customer phone | `transactions.customer_phone` |
| Product amount | `amount` (naira) |
| PAYLITY convenience fee | `fee_amount` (naira) |
| Gateway charge | `gateway_charge_amount` (naira), if > 0 |
| Total paid | `total_amount` (naira) |
| Status | Current transaction status |
| Timestamp | `updated_at` or fulfillment time |
| Fulfillment reference | If available |

### Receipt availability

| Status | Receipt shown |
|--------|---------------|
| `payment_success` | Payment receipt (pending delivery) |
| `fulfillment_pending` | Payment receipt (processing) |
| `fulfilled` | Final receipt with fulfillment ref |
| `failed` | Failure receipt with reason |
| `payment_failed` | No success receipt — show error state |

---

## 13. Frontend Integration Contract

Aligns with existing checkout engine in `apps/web`.

### Frontend sends (on Pay click — PAY-007+)

Replace disabled Pay CTA with call to `POST /api/checkout/initialize`.

**Shared fields (all products):**

| Field | Type | Required |
|-------|------|----------|
| `product_type` | `airtime` \| `data` \| `electricity` | Yes |
| `customer_phone` | string | Yes |
| `customer_email` | string | No |
| `payload` | object | Yes |

**Payload by product (matches PAY-004 checkout state):**

| product_type | payload keys |
|--------------|--------------|
| `airtime` | `network`, `recipient_phone`, `amount` (naira integer) |
| `data` | `network`, `recipient_phone`, `data_plan_id` |
| `electricity` | `disco`, `meter_type`, `meter_number`, `customer_name`, `amount` (naira integer) |

**Notes:**
- Frontend sends product amounts in **naira** (whole numbers). Backend converts to kobo.
- Frontend `useMyNumber` toggle resolves to `recipient_phone = customer_phone` before API call.
- Guest limit applies to **product amount only** (`amount`). Fees and gateway charges are added on top.
- Frontend should display three lines on review: product amount, convenience fee, gateway charge (if any), then total payable.
- Frontend fee display should update from API response (₦100 convenience fee per product), not hardcoded `0`.

### Frontend expects back

**From initialize:**

| Field | Use |
|-------|-----|
| `reference` | Display on pending/success screens, poll key |
| `amount_naira` | Confirm product amount; enforce guest cap client-side |
| `fee_amount_naira` | Show PAYLITY convenience fee |
| `gateway_charge_amount_naira` | Show gateway charge when present |
| `total_amount_naira` | Final amount customer pays at checkout |
| `status` | Drive UI state (`payment_pending`) |
| `payment.authorization_url` | Redirect to Paystack when live |
| `payment.message` | Show placeholder until integration |

**From GET transaction:**

| Field | Use |
|-------|-----|
| `status` | Success / failed / pending UI |
| `failure_reason` | Error message |
| `receipt` | Receipt card on success page |

### Status mapping (frontend)

| Backend status | Frontend step |
|----------------|-----------------|
| `payment_pending` | Processing / awaiting payment |
| `payment_success`, `fulfillment_pending` | Pending delivery |
| `fulfilled` | Success |
| `payment_failed`, `failed`, `cancelled` | Failed |

### Polling (future)

After Paystack redirect, frontend polls `GET /api/transactions/{reference}` every 3s until terminal status or 60s timeout.

---

## 14. PAY-006 Implementation Requirements

Acceptance criteria for Laravel API scaffold (next milestone):

### Project setup

- [ ] Laravel API app scaffolded under `apps/api` (or agreed monorepo path)
- [ ] `.env.example` with DB, app URL, placeholder Paystack/VTPass keys
- [ ] CORS configured for `apps/web` origin

### Database

- [ ] `transactions` migration matches §6 (includes `gateway_charge_amount`)
- [ ] Money stored in kobo (integers)
- [ ] Unique index on `reference`

### Core services

- [ ] `TransactionService` — create, update status, generate `PYL-YYYYMMDD-XXXXXX` reference
- [ ] `FeeService` — ₦100 convenience fee for all v1 products; `total_amount = amount + fee_amount + gateway_charge_amount`
- [ ] `FraudService` — guest product cap ₦10,000 on `amount`; phone daily ₦20,000 and IP daily ₦30,000 on `amount` sums
- [ ] `PaymentService` — placeholder initialize (no live Paystack)
- [ ] `FulfillmentService` — stub fulfill method (no live VTPass)
- [ ] `ReceiptService` — receipt payload builder

### Product adapters

- [ ] `ProductAdapterInterface` defined
- [ ] `AirtimeAdapter`, `DataAdapter`, `ElectricityAdapter` implemented
- [ ] `ProductAdapterRegistry` resolves adapter by `product_type`
- [ ] Invalid product type returns `422`

### Controllers & routes

- [ ] `POST /api/checkout/initialize` — full flow through §10
- [ ] `GET /api/transactions/{reference}` — status + receipt payload
- [ ] `POST /api/payments/paystack/callback` — stub handler
- [ ] `POST /api/payments/paystack/webhook` — stub handler

### Validation

- [ ] Nigerian phone normalization (+234 → 0)
- [ ] Product-specific payload validation in adapters
- [ ] Minimum amount ₦50 enforced server-side
- [ ] Fraud blocks payment init on rule failure

### API quality

- [ ] Consistent JSON response envelope (`success`, `data`, `error`)
- [ ] Form request or validator classes for initialize endpoint
- [ ] Feature tests for initialize happy path per product type
- [ ] Feature test for guest product amount limit rejection (`amount > ₦10,000`)
- [ ] Feature test that ₦10,000 product + fees is allowed for guest checkout

### Scope guardrails (must NOT build in PAY-006)

- [ ] No wallet
- [ ] No user registration / login
- [ ] No admin dashboard
- [ ] No live Paystack charges
- [ ] No live VTPass fulfillment
- [ ] No OTP verification flow (flag only)

### Documentation

- [ ] README in `apps/api` with local setup and example curl commands

---

## Appendix: Error Codes

| Code | HTTP | Message |
|------|------|---------|
| `VALIDATION_ERROR` | 422 | The given data was invalid. |
| `GUEST_LIMIT_EXCEEDED` | 422 | Guest product amount is limited to ₦10,000 |
| `PHONE_DAILY_LIMIT_EXCEEDED` | 422 | Daily limit reached for this phone number |
| `IP_DAILY_LIMIT_EXCEEDED` | 422 | Daily limit reached for this device |
| `OTP_REQUIRED` | 422 | Phone verification required for this amount |
| `TRANSACTION_NOT_FOUND` | 404 | Transaction not found |
| `INVALID_SIGNATURE` | 401 | Invalid Paystack webhook signature |
| `PAYMENT_FAILED` | 402 | Payment could not be completed |
| `FULFILLMENT_FAILED` | 502 | Payment received but delivery failed |

---

*Document owner: PAY-005 · Ready for PAY-006 Laravel implementation.*
