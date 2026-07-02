# PAYLITY NG — Product Roadmap

Simple roadmap aligned with current MVP reality. Scope discipline keeps PAYLITY fast to market.

---

## MVP (locked — current release)

**Goal:** Guest utility payments with Paystack checkout and VTPass delivery foundation.

| In scope | Status |
|----------|--------|
| Airtime, Data, Electricity checkout | ✅ |
| Guest checkout (no login) | ✅ |
| Paystack pay + backend verify | ✅ |
| VTPass fulfill foundation (manual) | ✅ |
| Transaction status page | ✅ |
| Pricing: product + ₦100 convenience fee | ✅ |
| Guest product limit ₦10,000 | ✅ |
| System build identity | ✅ |

**Explicitly out of scope for MVP**

- Betting
- Wallet / stored balance
- User dashboard / order history account
- Loyalty / rewards
- Complex analytics / BI
- Email/SMS receipts (print only)
- Auto-fulfillment by default
- Admin UI

---

## Phase 2 — Operations & live vending

**Recommended next: PAY-013 Internal Operations Console**

| Item | Purpose |
|------|---------|
| Admin operations console | Search reference, view timeline, manual fulfill, notes |
| Live VTPass vending | Production catalog, tested variation codes |
| Auto-fulfill policy | Enable `FEATURE_VTPASS_AUTO_FULFILL` with safeguards |
| TV subscriptions | New product adapter + checkout tab |
| OTP verification | Unlock higher product limits (`verified_phone`) |
| Email/SMS receipts | Post-payment notifications |
| VTPass merchant verify in checkout | Real electricity meter validation before pay |
| API rate limiting | Protect checkout initialize |
| Secure fulfill endpoint | Auth or IP allowlist |

**Exit criteria:** 50+ successful live fulfillments with <2% manual intervention.

---

## Phase 3 — Customer retention

| Item | Purpose |
|------|---------|
| User accounts | Optional login, order history |
| Saved beneficiaries | Quick repeat airtime/data/electricity |
| Referral program | Growth loop |
| Mobile app (React Native / PWA) | Native-like experience, push promos |

**Still no wallet** in Phase 3 unless regulatory review completed.

---

## Phase 4 — Platform & B2B

| Item | Purpose |
|------|---------|
| Wallet (if approved) | Stored balance, faster repeat pay |
| API marketplace | Partners integrate PAYLITY engine |
| B2B widgets | Embeddable checkout for agents/resellers |
| Advanced fraud / ML | Velocity, device fingerprinting |
| Multi-currency (if ever) | Beyond NGN |

---

## Milestone map (completed → next)

```
PAY-001..005  Spec & blueprint
PAY-006..007  Laravel API + frontend integration
PAY-008..009  Paystack init + verify
PAY-010       VTPass foundation
PAY-011       Sandbox E2E + status page + UI polish + build identity
PAY-012       Launch documentation suite  ← you are here
PAY-013       Internal Operations Console  ← recommended next
```

---

## Decision log (keep updated)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Auth model | Guest-only MVP | Faster launch |
| Payment provider | Paystack | Nigeria standard |
| Fulfillment provider | VTPass | Airtime/data/discos |
| Auto-fulfill default | Off | Safety |
| DB local dev | SQLite | Simplicity |
| DB production | PostgreSQL | Concurrency |
| Wallet | Deferred | Compliance complexity |

---

## What we will not build (unless scope formally changes)

- Sports betting payments
- Crypto checkout
- P2P transfers
- Loan/credit products
- Full CRM / marketing automation in v1

---

*Document: PAY-012 · Roadmap · Next milestone: **PAY-013 Internal Operations Console***
