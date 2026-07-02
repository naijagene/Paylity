# PAYLITY NG — Go-Live Checklist

Use this checklist before soft launch and again before public launch.  
Check `[x]` only when verified in the target environment.

---

## Domain & infrastructure

- [ ] Production domain registered (`paylity.ng` or chosen domain)
- [ ] DNS configured for web and API subdomains
- [ ] **SSL** certificate installed on web
- [ ] **SSL** certificate installed on API
- [ ] HTTPS forced; no mixed content warnings
- [ ] Production server provisioned (API + Web + DB)
- [ ] Database migrated (PostgreSQL/MySQL — not SQLite)
- [ ] **Backups** enabled and restore tested

---

## Paystack (live)

- [ ] Live **Paystack public key** in server env (frontend if needed for future inline)
- [ ] Live **Paystack secret key** on API only
- [ ] `FEATURE_PAYSTACK=true`
- [ ] **Paystack callback URL** set: `https://<web-domain>/payment/callback`
- [ ] **Paystack webhook URL** set: `https://<api-domain>/api/v1/payments/paystack/webhook`
- [ ] Webhook secret matches; test event received and verified
- [ ] Live test transaction completed (small amount)
- [ ] Verify endpoint confirms payment before success UI

---

## VTPass

- [ ] **VTPass sandbox E2E complete** (`docs/PAY-011-SANDBOX-E2E-TEST.md`)
- [ ] Live **VTPass credentials** configured (when ready for live vend)
- [ ] `VTPASS_BASE_URL=https://vtpass.com` for production
- [ ] `FEATURE_VTPASS=true` (only when ready to deliver)
- [ ] **`FEATURE_VTPASS_AUTO_FULFILL=false`** confirmed unless explicitly approved
- [ ] Data plan variation codes mapped to live VTPass catalog
- [ ] Electricity disco service IDs validated
- [ ] Manual fulfill tested on staging with live/sandbox creds
- [ ] Fulfill endpoint access restricted (not public open internet)

---

## Feature flags confirmed

- [ ] `FEATURE_PAYSTACK` — intended value documented
- [ ] `FEATURE_VTPASS` — intended value documented
- [ ] `FEATURE_VTPASS_AUTO_FULFILL=false` for initial launch
- [ ] Frontend `NEXT_PUBLIC_ENVIRONMENT=Production`
- [ ] Build number / version bumped and visible in footer

---

## Application configuration

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] `NEXT_PUBLIC_API_BASE_URL` points to production API
- [ ] CORS allows production web origin only
- [ ] **Support WhatsApp** configured (`NEXT_PUBLIC_WHATSAPP_URL`)
- [ ] Placeholder WhatsApp number replaced with real support line
- [ ] `APP_VERSION` / `APP_BUILD` match release tag

---

## Quality & error handling

- [ ] **Error handling checked** on checkout, callback, transaction status
- [ ] Offline API message displays correctly
- [ ] Transaction not found page works
- [ ] Payment failed page works
- [ ] `php artisan test` passes on release branch
- [ ] `npm run lint` and `npm run build` pass
- [ ] Mobile responsive check on checkout + success + status pages

---

## Operations readiness

- [ ] `OPERATIONS-RUNBOOK.md` reviewed by support
- [ ] Manual fulfill procedure tested by ops
- [ ] Refund escalation path agreed (Paystack dashboard)
- [ ] Engineering on-call contact defined for launch day
- [ ] **PAY-013 Internal Operations Console** — recommended before scale (not blocking tiny soft launch)

---

## Smoke test complete

- [ ] Health check returns correct build info
- [ ] Full flow: checkout → Paystack → verify → status page
- [ ] Manual fulfill → `fulfilled` (if VTPass enabled)
- [ ] Print receipt works on success page
- [ ] System identity footer shows correct environment

---

## Launch approvals

- [ ] **Soft launch approved** (founder sign-off)  
  _Limited users, manual ops, monitoring active_

- [ ] **Public launch approved** (founder sign-off)  
  _Marketing live, auto-fulfill policy decided, ops console ready_

---

## Post-launch (first 24 hours)

- [ ] Monitor Laravel logs for webhook/verify errors
- [ ] Review all `failed` transactions
- [ ] Confirm no duplicate charges reported
- [ ] Support WhatsApp response time < 15 min during window

---

**Launch date:** _______________  
**Approved by:** _______________  
**Build deployed:** _______________

---

*Document: PAY-012 · Go-Live Checklist*
