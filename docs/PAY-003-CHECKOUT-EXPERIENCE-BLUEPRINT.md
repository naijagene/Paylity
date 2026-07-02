# PAY-003 — Universal Checkout Engine UX Blueprint

**Product:** PAYLITY NG  
**Milestone:** PAY-003 (Documentation)  
**Next implementation:** PAY-004  
**Status:** Ready for frontend build

---

## 1. Product Goal

Build one **Universal Checkout Engine** that powers Airtime, Data, and Electricity purchases without login, wallet, or dashboard.

**Success criteria for the user:**
- Complete a payment in under 30 seconds on mobile.
- Understand what they are paying for before confirming.
- Trust the platform enough to pay without creating an account.

**Success criteria for the business:**
- One reusable checkout flow reduces build time for new services.
- Guest checkout removes friction and increases conversion.
- ₦10,000 guest product amount cap limits fraud exposure until OTP is built (fees excluded).

**Out of scope for v1:** Login, wallet, dashboard, betting checkout, backend integration (PAY-004 is UI + client-side validation only unless backend is ready).

---

## 2. Customer Journey

```
Home → Select service → Checkout (fill form) → Review → Pay → Result (Success / Failed / Pending)
```

| Step | User action | System response |
|------|-------------|-----------------|
| 1 | Tap **Buy Airtime**, **Buy Data**, or **Pay Electricity** on homepage | Navigate to `/checkout?product={type}` |
| 2 | Select network / disco, enter recipient details, choose product amount or plan | Inline validation, live pricing breakdown |
| 3 | Tap **Continue** | Show **Review** screen with editable summary |
| 4 | Tap **Pay Now** | Show payment pending state; initiate payment (mock in PAY-004 if no API) |
| 5a | Payment succeeds | Success page + receipt actions |
| 5b | Payment fails | Failed page with retry path |
| 5c | Payment pending | Pending page with status polling placeholder |

**Guest default:** No account prompt at any step. Email/phone collected only when needed for receipt or payment.

---

## 3. Checkout Route Strategy

### Primary route

```
/checkout?product=airtime
/checkout?product=data
/checkout?product=electricity
```

### Redirect rules

| Source | Target |
|--------|--------|
| Homepage service cards | `/checkout?product=airtime` etc. |
| Legacy placeholder routes `/airtime`, `/data`, `/electricity` | Redirect to matching `/checkout?product=...` (PAY-004) |
| Invalid or missing `product` | `/checkout?product=airtime` with product picker visible, or dedicated "Select a service" empty state |

### Supported `product` values (v1)

| Value | Label |
|-------|-------|
| `airtime` | Buy Airtime |
| `data` | Buy Data |
| `electricity` | Pay Electricity |

### Query params (future-ready)

| Param | Purpose | v1 |
|-------|---------|-----|
| `product` | Required. Determines field schema | Yes |
| `step` | Optional. `form` \| `review` \| `pay` | Optional deep-link |
| `amount` | Pre-fill amount (promo links) | Optional |

### Page shell

Single page component: **`CheckoutPage`**. Product type switches field schema, labels, and review summary — not separate page trees.

---

## 4. Universal Checkout Flow

Five internal steps rendered as one continuous mobile flow (step indicator optional, not required for v1).

```
┌─────────────────────────────────────┐
│  Header: product name + back home   │
├─────────────────────────────────────┤
│  Step A: Product selector (tabs)    │  ← switch product without leaving checkout
├─────────────────────────────────────┤
│  Step B: Dynamic form fields        │  ← product-specific schema
├─────────────────────────────────────┤
│  Step C: Amount / plan selection    │  ← quick picks + custom (where allowed)
├─────────────────────────────────────┤
│  Step D: Review & Pay               │  ← full summary before payment
├─────────────────────────────────────┤
│  Step E: Result                     │  ← success / failed / pending
└─────────────────────────────────────┘
```

### Step behavior

| Step | CTA | Validation |
|------|-----|------------|
| Form | **Continue to Review** | All required fields valid; productAmount within guest limit |
| Review | **Pay ₦{payableAmount}** | Re-validate; block if productAmount > ₦10,000 guest limit |
| Pay | — | Full-screen pending overlay |
| Result | **Done** / **Try Again** / **New Payment** | — |

### State model (PAY-004)

```ts
CheckoutState = {
  product: 'airtime' | 'data' | 'electricity'
  step: 'form' | 'review' | 'processing' | 'success' | 'failed' | 'pending'
  fields: Record<string, string>
  productAmount: number     // airtime/data/electricity purchase amount (naira)
  convenienceFee: number    // flat PAYLITY fee (₦100 in v1)
  gatewayFee: number        // payment gateway charge (0 until Paystack)
  payableAmount: number     // productAmount + convenienceFee + gatewayFee
  transactionRef: string | null
  error: CheckoutError | null
}
```

### Official money policy

```
productAmount + convenienceFee + gatewayFee = payableAmount
```

| Field | v1 value | Guest limit applies? |
|-------|----------|----------------------|
| `productAmount` | User-selected or plan price | **Yes — max ₦10,000** |
| `convenienceFee` | ₦100 flat | No |
| `gatewayFee` | 0 until Paystack; passed to customer | No |
| `payableAmount` | Sum of above | No — may exceed ₦10,000 |

Persist form state in **sessionStorage** so refresh on review step does not lose data.

---

## 5. Product-Specific Field Definitions

### Shared fields (all products)

| Field ID | Label | Type | Required | Notes |
|----------|-------|------|----------|-------|
| `customerPhone` | Your phone number | tel | Yes | Nigerian MSISDN; for receipt + support. Format: `080XXXXXXXX` |
| `customerEmail` | Email (optional) | email | No | Receipt delivery |

### Airtime

| Field ID | Label | Type | Required | Options / rules |
|----------|-------|------|----------|-----------------|
| `network` | Network | select | Yes | MTN, Airtel, Glo, 9mobile |
| `recipientPhone` | Phone to recharge | tel | Yes | 11-digit Nigerian number |
| `amount` | Amount | amount | Yes | Quick picks: ₦100, ₦200, ₦500, ₦1,000, ₦2,000, ₦5,000 + custom |

**Recipient = recharge target.** Default: same as `customerPhone` with "Use my number" toggle.

### Data

| Field ID | Label | Type | Required | Options / rules |
|----------|-------|------|----------|-----------------|
| `network` | Network | select | Yes | MTN, Airtel, Glo, 9mobile |
| `recipientPhone` | Phone number | tel | Yes | 11-digit Nigerian number |
| `dataPlan` | Data plan | plan-picker | Yes | Card list: name, size, validity, price (static mock in PAY-004) |
| `amount` | Amount | hidden/computed | Yes | Derived from selected plan |

**No custom amount for data in v1.** User picks a plan; amount is read-only.

### Electricity

| Field ID | Label | Type | Required | Options / rules |
|----------|-------|------|----------|-----------------|
| `disco` | Electricity provider | select | Yes | AEDC, EKEDC, IKEDC, PHED, etc. (static list v1) |
| `meterType` | Meter type | segmented | Yes | Prepaid \| Postpaid |
| `meterNumber` | Meter number | text | Yes | 10–13 digits, numeric |
| `customerName` | Customer name | text | Yes | From validation API later; free text in PAY-004 mock |
| `amount` | Amount | amount | Yes | Quick picks: ₦1,000, ₦2,000, ₦5,000, ₦10,000 + custom |

**Meter validation:** Show "Verify meter" button (mock success in PAY-004). Display verified name before review.

---

## 6. Validation Rules

### Phone numbers (Nigerian)

| Rule | Pattern |
|------|---------|
| Accept | `080...`, `081...`, `070...`, `090...`, `091...` (11 digits) |
| Normalize | Strip spaces/dashes; convert `+234` prefix to `0` |
| Error copy | "Enter a valid Nigerian phone number" |

### Amount (productAmount)

| Rule | Value |
|------|-------|
| Minimum productAmount | ₦50 (airtime/data plan minimum may override) |
| Maximum productAmount (guest) | ₦10,000 |
| Maximum productAmount (post-OTP, future) | Product-specific (TBD) |
| Format | Whole naira only in v1 (no kobo input) |
| Error (min) | "Minimum amount is ₦{min}" |
| Error (max guest) | "Guest checkout supports purchases up to ₦10,000. Please verify your phone number via OTP to continue." |

**Not subject to guest limit:** `convenienceFee`, `gatewayFee`, `payableAmount`.

**Allowed guest example:** ₦10,000 productAmount + ₦100 convenienceFee + gatewayFee = payableAmount above ₦10,000 ✓

### Meter number (electricity)

| Rule | Value |
|------|-------|
| Length | 10–13 digits |
| Characters | Numeric only |
| Error | "Enter a valid meter number" |

### Email (optional)

| Rule | Value |
|------|-------|
| Format | Standard email regex |
| Error | "Enter a valid email address" |

### Data plan

| Rule | Value |
|------|-------|
| Required | Must select one plan before continue |
| Error | "Select a data plan to continue" |

### Network / disco

| Rule | Value |
|------|-------|
| Required | Must select before continue |
| Error | "Select a {network/provider}" |

### Validation timing

- **On blur:** Field-level errors.
- **On Continue:** Full form validation; scroll to first error.
- **On Pay:** Re-validate entire state.

---

## 7. ₦10,000 Guest Product Amount Limit

### Rules

1. Guest limit applies to **`productAmount` only** — not `payableAmount`.
2. Block checkout when `productAmount > 10_000` and phone is unverified.
3. `convenienceFee` (₦100) and `gatewayFee` never trigger the guest limit.
4. `payableAmount` may exceed ₦10,000. Example: ₦10,000 + ₦100 + gateway = allowed.
5. No OTP flow in v1 — show informational gate only.
6. User can reduce productAmount or contact support via WhatsApp.

### UI treatment

When productAmount exceeds limit:

```
┌──────────────────────────────────────────┐
│ ⚠  Guest limit reached                   │
│                                          │
│ Guest checkout supports purchases up to  │
│ ₦10,000. Please verify your phone number │
│ via OTP to continue.                     │
│                                          │
│ [ Reduce product amount ]  [ WhatsApp ]  │
└──────────────────────────────────────────┘
```

- **Continue / Pay button:** Disabled when `productAmount > 10_000`.
- **Inline on product amount field:** Show banner when `productAmount > 10_000`.
- **Fees excluded:** Do not include convenienceFee or gatewayFee in limit check.

### Future OTP hook (do not build until backend ready)

When OTP ships: productAmount > ₦10,000 triggers OTP modal before Pay. Reserve component slot: `OtpVerificationGate`.

---

## 8. Review Screen Design

### Layout (mobile)

```
┌─────────────────────────────────────┐
│ Review your payment                 │
├─────────────────────────────────────┤
│ ┌─────────────────────────────────┐ │
│ │ Product badge: Buy Airtime      │ │
│ │ Network: MTN                    │ │
│ │ Recipient: 0803 XXX XXXX        │ │
│ │ Product Amount: ₦1,000          │ │
│ │ Convenience Fee: ₦100           │ │
│ │ Gateway Charge: Calculated…     │ │
│ │ ─────────────────────────────── │ │
│ │ Total Payable: ₦1,100           │ │
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│ 🔒 Secure payment · Instant delivery│
├─────────────────────────────────────┤
│ [ Edit details ]                    │
│ [ Pay ₦1,000 ]                      │
└─────────────────────────────────────┘
```

### Summary fields by product

| Product | Show on review |
|---------|----------------|
| Airtime | Network, recipient phone, productAmount, convenienceFee, gatewayFee, payableAmount |
| Data | Network, recipient phone, plan name, validity, productAmount, convenienceFee, gatewayFee, payableAmount |
| Electricity | Disco, meter type, meter number, customer name, productAmount, convenienceFee, gatewayFee, payableAmount |

Gateway line shows amount when known, otherwise: **"Calculated securely during payment"**.

### Actions

| Action | Behavior |
|--------|----------|
| **Edit details** | Return to form step; preserve entered values |
| **Pay ₦{payableAmount}** | Primary CTA; full width; primary color `#F5B400` |
| **Back** | Browser back or header back → form step |

---

## 9. Payment Pending State

Full-screen overlay or dedicated pending view after **Pay** is tapped.

### Content

| Element | Copy / behavior |
|---------|-----------------|
| Icon | Animated spinner or pulse ring (primary color) |
| Headline | "Processing your payment" |
| Subtext | "Please don't close this page" |
| Reference | "Ref: PAY-{timestamp}" (generate client-side in PAY-004 mock) |
| Timeout | After 60s without response → move to **Pending** result page |

### Rules

- Disable all form interaction while pending.
- No back navigation without confirmation dialog: "Payment in progress. Are you sure you want to leave?"

---

## 10. Success Page Behavior

Replace checkout content or navigate to `/checkout/success?ref={ref}` (either pattern OK; prefer same route with `step=success` for state continuity).

### Content

| Element | Copy |
|---------|------|
| Icon | Green check `#16A34A` |
| Headline | "Payment successful" |
| Subtext | Product-specific: "Airtime sent to 0803 XXX XXXX" |
| Amount paid | "₦{payableAmount} paid" |
| Reference | "Transaction ref: PAY-XXXX" |
| Timestamp | Current date/time |

### Actions

| Button | Behavior |
|--------|----------|
| **Download receipt** | Generate/share receipt (see §12) |
| **Share receipt** | Web Share API or copy link (mobile) |
| **Make another payment** | Reset checkout → `/checkout?product={same}` |
| **Back to home** | `/` |

Auto-clear sessionStorage checkout state on success.

---

## 11. Failed Payment Behavior

### Content

| Element | Copy |
|---------|------|
| Icon | Red X `#DC2626` |
| Headline | "Payment failed" |
| Subtext | Dynamic from error (see §13) |
| Reference | If available |

### Actions

| Button | Behavior |
|--------|----------|
| **Try again** | Return to Review step with form data intact |
| **Edit details** | Return to form step |
| **Chat on WhatsApp** | Open support with pre-filled ref |
| **Back to home** | `/` |

Do not auto-retry payment without user action.

---

## 12. Receipt Behavior

### Receipt contents

| Field | Source |
|-------|--------|
| PAYLITY NG | Static |
| Transaction reference | checkout / backend state |
| Product | Product label |
| Customer phone | checkout fields |
| Product amount | `productAmount` |
| Convenience fee | `convenienceFee` |
| Gateway charge | `gatewayFee` or "Calculated securely during payment" |
| Total paid | `payableAmount` |
| Status | Transaction status |
| Timestamp | Payment completion time |

### Delivery (v1 UI only)

| Channel | v1 behavior |
|---------|-------------|
| On-screen | Receipt card on success page |
| Download | "Download receipt" → print-friendly view or PDF stub |
| Email | Show "Receipt sent to {email}" only if email provided (mock toast in PAY-004) |
| SMS | Placeholder copy: "SMS receipt coming soon" |

### Receipt component

Compact card, white background, rounded corners, monospace ref number.

---

## 13. Error Message Copy

Use these exact strings in PAY-004.

### Field validation

| Code | Message |
|------|---------|
| `PHONE_INVALID` | Enter a valid Nigerian phone number |
| `EMAIL_INVALID` | Enter a valid email address |
| `PRODUCT_AMOUNT_MIN` | Minimum amount is ₦{min} |
| `PRODUCT_AMOUNT_MAX_GUEST` | Guest checkout supports purchases up to ₦10,000. Please verify your phone number via OTP to continue. |
| `METER_INVALID` | Enter a valid meter number |
| `PLAN_REQUIRED` | Select a data plan to continue |
| `NETWORK_REQUIRED` | Select a network |
| `DISCO_REQUIRED` | Select an electricity provider |
| `NAME_REQUIRED` | Enter the customer name |
| `REQUIRED` | This field is required |

### Payment errors

| Code | Message |
|------|---------|
| `PAYMENT_DECLINED` | Your payment was declined. Try another card or payment method. |
| `PAYMENT_TIMEOUT` | Payment timed out. Check your bank app and try again. |
| `PAYMENT_PENDING` | Your payment is still processing. We'll update you shortly. |
| `NETWORK_ERROR` | Connection error. Check your internet and try again. |
| `UNKNOWN` | Something went wrong. Please try again or contact support. |

### Meter verification (mock)

| State | Message |
|-------|---------|
| Verifying | Verifying meter… |
| Success | Meter verified |
| Failed | Could not verify meter. Check the number and try again. |

---

## 14. Loading States

| Context | UI |
|---------|-----|
| Page load | Skeleton cards for form fields (2–3 shimmer rows) |
| Meter verify | Inline spinner on button; button text → "Verifying…" |
| Plan list | Skeleton plan cards |
| Pay | Full-screen pending overlay (§9) |
| Receipt download | Button spinner + "Preparing receipt…" |

**Rules:**
- Never show a blank screen.
- Loading indicators use primary `#F5B400` accent.
- Minimum visible loading duration: 300ms (avoid flash).

---

## 15. Mobile-First UX Rules

1. **Touch targets:** Minimum 44×44px for all interactive elements.
2. **Single column:** No multi-column forms on mobile; max content width `max-w-lg`.
3. **Sticky CTA:** Primary action pinned to bottom on form and review steps.
4. **Thumb zone:** Amount quick-picks in 2×3 grid; large tap areas.
5. **Keyboard:** Use `inputMode="numeric"` for phone, meter, amount fields.
6. **Labels:** Always visible (no placeholder-only labels).
7. **Progress:** User always knows product type (header badge) and current step.
8. **Scroll:** Auto-scroll to first validation error.
9. **No hover-only actions:** All actions visible without hover.
10. **Font:** Bold headings, 16px minimum body text on inputs (prevent iOS zoom).

---

## 16. Trust and Security Cues

Place consistently across checkout — not only on homepage.

| Cue | Placement | Copy |
|-----|-----------|------|
| Secure badge | Below Pay button | 🔒 Secure payment |
| Instant delivery | Trust strip on review | ⚡ Instant delivery |
| No registration | Subtext under headline | No account needed |
| Guest limit notice | Footer of product amount section | Guest product amount up to ₦10,000 |
| Provider logos | Optional network/disco row | Static icons (MTN, etc.) — no official logos unless licensed |

**Color usage:**
- Success states: `#16A34A`
- Errors: `#DC2626`
- Primary CTAs: `#F5B400` on white or `#121212` text
- Do not use red for non-error UI

---

## 17. Future Services Compatibility

The Universal Checkout Engine uses a **product schema registry**. Adding a service = new schema entry, not a new checkout app.

### Planned services

| Product key | Label | Unique fields (future) |
|-------------|-------|------------------------|
| `tv` | Pay TV | Provider (DSTV, GOtv, Startimes), smartcard/IUC number, package |
| `betting` | Fund Betting | Platform, account ID, amount (requires compliance review — out of v1) |
| `internet` | Internet | ISP, account/username, plan |
| `education` | Education | Institution, student ID, fee type, amount |

### Extension pattern

```ts
ProductSchema = {
  id: string
  label: string
  fields: FieldDefinition[]
  amountMode: 'quick-picks' | 'plan-picker' | 'custom'
  reviewFields: string[]
  guestMaxProductAmount: number  // default 10_000 — applies to productAmount only
}
```

### v1 constraints for future-proofing

- Product tabs on checkout must read from schema registry (not hardcoded if/else trees).
- Invalid `product` query → graceful fallback, not 404.
- Betting: schema slot reserved; **do not expose in UI until compliance sign-off**.

---

## 18. Exact Frontend Components Needed for PAY-004

### Pages / routes

| File | Purpose |
|------|---------|
| `src/app/checkout/page.tsx` | Universal checkout page (reads `product` query) |
| `src/app/checkout/success/page.tsx` | Optional; or inline success step |

### Layout components

| Component | Purpose |
|-----------|---------|
| `CheckoutPageShell` | Header, back link, product title, step wrapper |
| `CheckoutStepIndicator` | Optional: Form → Review → Pay |
| `StickyCheckoutFooter` | Pinned bottom CTA bar |

### Form components

| Component | Purpose |
|-----------|---------|
| `ProductTabs` | Switch airtime / data / electricity |
| `FormField` | Label + input + error wrapper |
| `PhoneInput` | Nigerian phone with normalization |
| `SelectField` | Network, disco dropdowns |
| `SegmentedControl` | Prepaid / postpaid |
| `AmountPicker` | Quick amount chips + custom input |
| `DataPlanPicker` | Plan card list |
| `MeterVerifyField` | Meter input + verify button + status |
| `GuestLimitBanner` | ₦10,000 cap warning |

### Review & payment

| Component | Purpose |
|-----------|---------|
| `CheckoutSummaryCard` | Review screen line items |
| `PayButton` | Primary pay CTA with amount label |
| `PaymentPendingOverlay` | Full-screen processing state |
| `CheckoutResult` | Shared success / failed / pending layout |

### Receipt

| Component | Purpose |
|-----------|---------|
| `ReceiptCard` | On-screen receipt display |
| `ReceiptActions` | Download, share, email buttons |

### Utilities / hooks

| Module | Purpose |
|--------|---------|
| `checkoutSchemas.ts` | Product field definitions |
| `checkoutValidation.ts` | All validation rules |
| `useCheckoutState.ts` | State + sessionStorage persistence |
| `formatNaira.ts` | Currency formatting (₦1,000) |
| `normalizePhone.ts` | Phone normalization |
| `generateTransactionRef.ts` | Client-side ref for mock payments |

### Reuse from PAY-002

| Existing | Use in checkout |
|----------|-----------------|
| `Button` | All CTAs |
| `PageContainer` | Checkout width constraint |
| `TrustBadge` | Review trust strip |

---

## 19. Acceptance Criteria for PAY-004 Implementation

### Routing

- [ ] `/checkout?product=airtime|data|electricity` renders correct product form
- [ ] Homepage service cards link to checkout routes (not placeholder pages)
- [ ] `/airtime`, `/data`, `/electricity` redirect to checkout with correct product
- [ ] Invalid `product` shows fallback (product picker or default to airtime)

### Form & validation

- [ ] All fields from §5 render per product
- [ ] All validation rules from §6 enforced with copy from §13
- [ ] Phone normalization works for `080…` and `+234…` inputs
- [ ] Data plan selection sets productAmount automatically
- [ ] Electricity meter verify shows loading + success/fail states (mock OK)

### Guest limit

- [ ] productAmount over ₦10,000 shows `GuestLimitBanner`
- [ ] Continue / Pay disabled when productAmount > ₦10,000
- [ ] ₦10,000 productAmount + ₦100 convenienceFee is allowed (payableAmount > ₦10,000)
- [ ] convenienceFee and gatewayFee never trigger guest limit
- [ ] WhatsApp CTA available from limit banner

### Flow

- [ ] Form → Review → Pay → Result flow completes without page reload
- [ ] Edit details returns to form with data preserved
- [ ] sessionStorage restores state on refresh during checkout

### Result pages

- [ ] Success page shows product-specific confirmation + receipt
- [ ] Failed page shows error message + retry actions
- [ ] Pending state shown after timeout or async mock

### UX

- [ ] Mobile-first: sticky CTA, 44px touch targets, single column
- [ ] Loading states per §14
- [ ] Trust cues per §16 on review step
- [ ] Uses PAYLITY brand colors

### Scope guardrails

- [ ] No login, wallet, dashboard, or betting UI
- [ ] No backend required for demo (mock payment acceptable)
- [ ] Product schema registry pattern used (not duplicated forms)

### Quality

- [ ] `npm run dev` works
- [ ] `npm run build` passes
- [ ] No TypeScript or ESLint errors

---

## Appendix: Visual Reference

| Token | Value |
|-------|-------|
| Primary | `#F5B400` |
| Dark | `#121212` |
| Background | `#FFFFFF` |
| Text | `#111827` |
| Success | `#16A34A` |
| Error | `#DC2626` |

**Card style:** `rounded-3xl`, light border `border-dark/5`, subtle shadow.  
**CTA style:** `rounded-2xl`, bold label, full-width on mobile.

---

*Document owner: PAY-003 · Ready for PAY-004 implementation.*
