# PAYLITY NG — System Architecture

Practical architecture reference for engineers and operators.

---

## High-level architecture

```mermaid
flowchart LR
    Customer[Customer Browser]
    Web[Next.js Web App]
    API[Laravel API]
    DB[(Database)]
    Paystack[Paystack]
    VTPass[VTPass]

    Customer --> Web
    Web --> API
    API --> DB
    API --> Paystack
    API --> VTPass
    Paystack --> Customer
    Paystack --> API
    VTPass --> API
```

**Monorepo layout**

| Path | Role |
|------|------|
| `apps/web` | Next.js 16 frontend (App Router, TypeScript, Tailwind) |
| `apps/api` | Laravel 12 API (PHP 8.2+) |
| `docs/` | Product and launch documentation |

---

## Frontend architecture

```
apps/web/src/
├── app/                    # Routes (/, /checkout, /payment/callback, /transaction/[ref])
├── components/
│   ├── checkout/           # Checkout engine UI
│   ├── payment/            # Payment callback client
│   ├── transaction/        # Status, receipt, timeline
│   └── system/             # Footer, build identity, about modal
├── hooks/                  # useCheckoutState
├── lib/
│   ├── api/                # API client, checkout, payments, transactions
│   ├── checkout/           # Pricing, validation, schemas
│   ├── system/             # buildInfo.ts
│   └── transaction/        # Display helpers
```

**Key behaviors**

- Checkout calls `POST /api/v1/checkout/initialize`
- Paystack redirect when `authorization_url` is returned
- Callback page calls `GET /api/v1/payments/paystack/verify/{reference}` — never trusts URL alone
- Transaction status page polls every 5s (max 2 min) while awaiting delivery
- Build identity from `NEXT_PUBLIC_*` env vars

---

## Backend architecture

```
apps/api/app/
├── Http/Controllers/Api/V1/
│   ├── CheckoutController.php
│   ├── PaystackController.php
│   ├── TransactionController.php
│   ├── FulfillmentController.php
│   └── HealthController.php
├── Services/
│   ├── TransactionService.php
│   ├── FeeService.php
│   ├── FraudService.php
│   ├── BuildInfoService.php
│   ├── Payments/
│   │   ├── PaystackService.php
│   │   └── PaymentVerificationService.php
│   └── Fulfillment/
│       ├── FulfillmentService.php
│       ├── VTPassService.php
│       └── Adapters/ (Airtime, Data, Electricity)
├── Models/Transaction.php
└── Support/ApiResponse.php
```

**API endpoints**

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/health` | Health + build info |
| POST | `/api/v1/checkout/initialize` | Create transaction + Paystack init |
| GET | `/api/v1/transactions/{reference}` | Transaction detail |
| POST | `/api/v1/transactions/{reference}/fulfill` | Manual VTPass fulfill |
| GET | `/api/v1/payments/paystack/verify/{reference}` | Verify payment |
| POST | `/api/v1/payments/paystack/webhook` | Paystack webhook |
| POST | `/api/v1/payments/paystack/callback` | Placeholder (no trust) |

**Money policy**

- Stored in DB as **naira integers**
- Sent to Paystack as **kobo** (`payable_amount × 100`)
- Guest limit: `product_amount` ≤ ₦10,000
- Convenience fee: ₦100 (v1 flat)

---

## Transaction lifecycle

```mermaid
stateDiagram-v2
    [*] --> created: Checkout initialize (Paystack off)
    [*] --> payment_pending: Checkout + Paystack init
    payment_pending --> payment_success: Paystack verify success
    payment_pending --> payment_failed: Paystack verify failed
    payment_success --> fulfillment_pending: Manual/auto fulfill start
    fulfillment_pending --> fulfilled: VTPass success
    fulfillment_pending --> failed: VTPass failure
    payment_success --> payment_success: Awaiting delivery (no fulfill yet)
    created --> failed: Paystack init error
```

---

## Paystack payment flow

```mermaid
sequenceDiagram
    participant U as User
    participant W as Web
    participant A as Laravel API
    participant P as Paystack

    U->>W: Initialize transaction
    W->>A: POST /checkout/initialize
    A->>A: Create transaction (payment_pending)
    A->>P: POST /transaction/initialize
    P-->>A: authorization_url
    A-->>W: reference + authorization_url
    W->>P: Redirect to Paystack
    U->>P: Pay
    P->>W: Redirect /payment/callback?reference=PYL-...
    W->>A: GET /payments/paystack/verify/{reference}
    A->>P: GET /transaction/verify/{reference}
    P-->>A: status, amount, reference
    A->>A: Validate reference + amount + NGN
    A-->>W: payment_success
    Note over P,A: Webhook charge.success also triggers verify
```

**Rules**

- Callback URL visit does **not** confirm payment
- PAYLITY reference is used as Paystack reference
- Webhook validates `X-Paystack-Signature` (HMAC SHA512)

---

## VTPass fulfillment flow

```mermaid
sequenceDiagram
    participant O as Operator/System
    participant A as Laravel API
    participant V as VTPass

    Note over A: Transaction must be payment_success
    O->>A: POST /transactions/{ref}/fulfill
    A->>A: status = fulfillment_pending
    A->>A: Adapter builds payload
    A->>V: POST /api/pay
    V-->>A: success/failure
    alt success
        A->>A: status = fulfilled
    else failure
        A->>A: status = failed + failure_reason
    end
```

**Adapters**

| Product | VTPass mapping |
|---------|----------------|
| Airtime | Network → serviceID (mtn, airtel, glo, etisalat) |
| Data | `{network}-data` + `variation_code` from `data_plan_id` |
| Electricity | Disco → serviceID + meter_number + meter_type |

**Safety defaults**

- `FEATURE_VTPASS=false` — fulfillment disabled
- `FEATURE_VTPASS_AUTO_FULFILL=false` — no auto-vend after payment

---

## Feature flags

| Flag | Default | Effect |
|------|---------|--------|
| `FEATURE_PAYSTACK` | `true` in example | Paystack init on checkout |
| `FEATURE_VTPASS` | `false` | Enables fulfill endpoint |
| `FEATURE_VTPASS_AUTO_FULFILL` | `false` | Auto-fulfill after payment verify |

Frontend flags are display-only via `NEXT_PUBLIC_ENVIRONMENT` (Sandbox vs Production label).

---

## CORS and auth

- **No user authentication** in MVP — all checkout is guest
- CORS configured for local dev (`localhost:3000` → API)
- Fulfill endpoint is **unauthenticated** — must be network-restricted or protected in production (IP allowlist, internal token — future PAY-013)

---

*Document: PAY-012 · System Architecture*
