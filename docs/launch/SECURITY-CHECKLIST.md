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

- [ ] **CORS** restricted to production web origin via `FRONTEND_URL` (localhost allowed in non-production only)
- [x] **Rate limiting** on checkout, transaction lookup, payment verify, and ops endpoints (PAY-014)
- [x] Public fulfill endpoint removed — manual fulfillment via ops console only (PAY-014)
- [x] Provider errors sanitized in production (`ProviderErrorSanitizer`)
- [ ] Error responses do not leak stack traces (`APP_DEBUG=false`)
- [ ] Health endpoint acceptable to expose publicly (no secrets in response)
- [ ] Run `php artisan paylity:preflight` before deploy — must pass with no FAIL items

---

## Logging & monitoring

- [ ] Laravel logs written to persistent storage
- [ ] Log rotation configured
- [ ] Paystack webhook failures logged (`Log::warning`) — reference and error code only, no secrets
- [ ] Auto-fulfill failures logged
- [ ] Provider configuration/API errors logged server-side (`ProviderErrorSanitizer::logProviderError`)
- [ ] Alert on repeated 5xx from API (recommended — not built in MVP)
- [ ] Do not log full Paystack/VTPass secret keys or card data
- [ ] Raw provider responses may be stored in `response_payload` — never log secret keys

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
- [x] Privacy / Terms MVP placeholder pages at `/privacy` and `/terms` (legal review still required)
- [ ] `NEXT_PUBLIC_WHATSAPP_URL` set in production (placeholder number hidden when unset)
- [x] Security headers configured in Next.js (`X-Frame-Options`, `X-Content-Type-Options`, etc.)

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
- [x] Authenticated admin console (PAY-013 ops console)
- [x] API rate limiting (Laravel throttle middleware) — PAY-014
- [x] Fulfill endpoint authentication (ops-only) — PAY-014
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
