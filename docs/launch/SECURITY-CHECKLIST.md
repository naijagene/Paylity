# PAYLITY NG — Security Checklist

Pre-launch security review. Check each item before public go-live.

---

## Transport & infrastructure

- [ ] **HTTPS** enabled on web domain (`paylity.ng`)
- [ ] **HTTPS** enabled on API domain (`api.paylity.ng`)
- [ ] HTTP → HTTPS redirect configured
- [ ] TLS 1.2+ only; valid certificate not expiring within 30 days
- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` on API
- [ ] Production database not publicly accessible

---

## Secrets & environment

- [ ] `PAYSTACK_SECRET_KEY` only on server (never in git, never in frontend)
- [ ] `VTPASS_PASSWORD` / `VTPASS_API_KEY` only on server
- [ ] `APP_KEY` unique per environment
- [ ] `.env` files in `.gitignore` (verified)
- [ ] CI/CD injects secrets from vault, not repo
- [ ] Frontend uses only `NEXT_PUBLIC_*` — no secret keys exposed

---

## Payment security

- [ ] Paystack **Verify Transaction** called server-side before `payment_success`
- [ ] Frontend callback does **not** mark payment successful alone
- [ ] Paystack amount validated against `payable_amount` (kobo)
- [ ] Paystack reference validated against PAYLITY reference
- [ ] Currency validated as NGN
- [ ] **Paystack webhook** validates `X-Paystack-Signature` (HMAC SHA512)
- [ ] Invalid webhook signature returns 401
- [ ] Paystack callback route is placeholder only (no status update)

---

## Fraud & limits

- [ ] Guest **product amount** capped at ₦10,000 (`FraudService`)
- [ ] Limit applies to product amount only (fees excluded from cap)
- [ ] Fraud checks run on checkout initialize (phone + IP patterns in engine)
- [ ] **OTP / verified phone** not built — do not raise guest limit without OTP (future)
- [ ] No wallet — reduces stored-value regulatory exposure ✅ (by design)

---

## API hardening

- [ ] **CORS** restricted to production web origin(s) only (remove localhost)
- [ ] **Rate limiting** on `/checkout/initialize` — ⚠️ **not yet implemented** (blocker for high-traffic launch)
- [ ] Fulfill endpoint **not exposed to public internet** without IP restriction or auth — ⚠️ **required before launch**
- [ ] Error responses do not leak stack traces (`APP_DEBUG=false`)
- [ ] Health endpoint acceptable to expose publicly (no secrets in response)

---

## Logging & monitoring

- [ ] Laravel logs written to persistent storage
- [ ] Log rotation configured
- [ ] Paystack webhook failures logged (`Log::warning`)
- [ ] Auto-fulfill failures logged
- [ ] Alert on repeated 5xx from API (recommended — not built in MVP)
- [ ] Do not log full Paystack/VTPass secret keys or card data

---

## Database & backups

- [ ] Automated daily DB backups enabled
- [ ] Backup restore tested at least once
- [ ] Transactions table included in backup scope
- [ ] Migration strategy documented for zero-downtime (future)

---

## Access control

- [ ] SSH access to servers limited to ops/engineering
- [ ] Paystack dashboard: 2FA enabled, limited team access
- [ ] VTPass dashboard: limited team access
- [ ] No shared admin passwords
- [ ] **No admin UI in MVP** — fulfill via secured internal access only

---

## Frontend

- [ ] No payment secrets in browser bundle
- [ ] External links (WhatsApp) use `rel="noopener noreferrer"`
- [ ] Build identity shows Sandbox vs Production clearly
- [ ] Privacy / Terms placeholders replaced before marketing push (legal review)

---

## MVP risk reductions (already in place)

| Control | Status |
|---------|--------|
| No wallet | ✅ Not built |
| No user accounts | ✅ Reduces credential theft scope |
| No betting | ✅ Out of scope |
| Guest-only checkout | ✅ Simpler PCI surface (Paystack handles cards) |
| Manual fulfill default | ✅ Prevents accidental mass live vend |

---

## Future requirements (post-MVP)

- [ ] **OTP verification** for product amounts above ₦10,000
- [ ] Authenticated admin console (PAY-013)
- [ ] API rate limiting (Laravel throttle middleware)
- [ ] Fulfill endpoint authentication
- [ ] WAF / DDoS protection at edge
- [ ] PCI: remain SAQ A (card fields on Paystack hosted page only)

---

## Sign-off

| Role | Name | Date | Signed |
|------|------|------|--------|
| Engineering | | | |
| Operations | | | |
| Founder | | | |

---

*Document: PAY-012 · Security Checklist*
