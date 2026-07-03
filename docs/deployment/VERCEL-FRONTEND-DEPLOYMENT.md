# Vercel Frontend Deployment — PAYLITY NG Staging

Deploy the PAYLITY Next.js app (`apps/web`) to **Vercel** at:

**`https://staging.paylity.ng`**

Part of the hybrid staging path: [HYBRID-STAGING-DEPLOYMENT.md](./HYBRID-STAGING-DEPLOYMENT.md)

The Laravel API remains on cPanel at `https://api-staging.paylity.ng`.

---

## Prerequisites

| Requirement | Notes |
|-------------|-------|
| Vercel account | Connected to GitHub |
| GitHub repository | PAYLITY monorepo |
| DNS access | cPanel Zone Editor for `paylity.ng` |
| API deployed | cPanel API live and health check passing |

---

## 1. Import project

1. Log in to [Vercel Dashboard](https://vercel.com/dashboard)
2. **Add New → Project**
3. Import the PAYLITY GitHub repository
4. Configure monorepo settings (see below)

---

## 2. Project settings

| Setting | Value |
|---------|-------|
| **Framework Preset** | Next.js |
| **Root Directory** | `apps/web` |
| **Build Command** | `npm run build` |
| **Install Command** | `npm ci` (or default) |
| **Output Directory** | *(leave default — Next.js)* |
| **Node.js Version** | 20.x (Project Settings → General) |

Vercel auto-detects Next.js when root is `apps/web`.

---

## 3. Environment variables

Set in **Project → Settings → Environment Variables** for **Preview** and **Production** (staging uses Production deployment on custom domain).

Copy from [STAGING-ENV-TEMPLATE.md](./STAGING-ENV-TEMPLATE.md):

| Variable | Staging value |
|----------|---------------|
| `NEXT_PUBLIC_API_BASE_URL` | `https://api-staging.paylity.ng/api/v1` |
| `NEXT_PUBLIC_OPERATOR_API_BASE_URL` | `https://api-staging.paylity.ng/api/v1` |
| `NEXT_PUBLIC_SITE_URL` | `https://staging.paylity.ng` |
| `NEXT_PUBLIC_APP_NAME` | `PAYLITY NG` |
| `NEXT_PUBLIC_APP_VERSION` | `1.0.0-rc1` |
| `NEXT_PUBLIC_BUILD_NUMBER` | `2026.07.03-rc1` |
| `NEXT_PUBLIC_BUILD_DATE` | `2026-07-03` |
| `NEXT_PUBLIC_ENVIRONMENT` | `Staging` |
| `NEXT_PUBLIC_GIT_COMMIT` | *(optional — CI can inject)* |
| `NEXT_PUBLIC_SUPPORT_EMAIL` | `support@paylity.ng` |
| `NEXT_PUBLIC_WHATSAPP_URL` | Real WhatsApp link or leave empty |

**Important:** `NEXT_PUBLIC_*` variables are embedded at **build time**. Redeploy after changing them.

Do **not** put backend secrets (`PAYSTACK_SECRET_KEY`, `OPERATOR_ACCESS_KEY`, VTPass password) in Vercel — those belong only on the Laravel `.env`.

---

## 4. Deploy

1. Click **Deploy**
2. Wait for build to complete (`npm run build` must pass)
3. Note the `*.vercel.app` preview URL for initial testing

Verify build logs show no missing env warnings for required public vars.

---

## 5. Custom domain

1. **Project → Settings → Domains**
2. Add `staging.paylity.ng`
3. Vercel shows required DNS record — typically:

   | Type | Name | Value |
   |------|------|-------|
   | **CNAME** | `staging` | `cname.vercel-dns.com` |

4. Add the CNAME in **cPanel → Zone Editor** (see [HYBRID-STAGING-DEPLOYMENT.md](./HYBRID-STAGING-DEPLOYMENT.md))
5. Wait for DNS + SSL (Vercel issues certificate automatically)

---

## 6. Verify frontend → API connectivity

Open browser devtools on `https://staging.paylity.ng`:

1. Homepage loads with logo and hero
2. Navigate to `/checkout?product=airtime`
3. Submit checkout — network tab should call `https://api-staging.paylity.ng/api/v1/checkout/initialize`
4. No CORS errors (API `FRONTEND_URL` must match)

Quick API check from terminal:

```bash
curl -s https://api-staging.paylity.ng/api/v1/health | jq .data.version
```

---

## 7. Paystack callback (frontend route)

Paystack redirects customers to:

```
https://staging.paylity.ng/payment/callback?reference=PYL-...
```

This is a Next.js route — no extra Vercel rewrite needed. Confirm in Paystack test dashboard:

| Setting | URL |
|---------|-----|
| Callback | `https://staging.paylity.ng/payment/callback` |

Backend `.env` on cPanel must mirror:

```env
PAYSTACK_CALLBACK_URL=https://staging.paylity.ng/payment/callback
```

---

## 8. Ops console

Ops UI is served by Vercel at:

```
https://staging.paylity.ng/ops
```

It calls the same API with `NEXT_PUBLIC_OPERATOR_API_BASE_URL`. The **operator secret** is entered in the browser (not stored in Vercel env).

---

## 9. Static assets

These ship with the Next.js build from `apps/web/public/`:

- `/brand/paylity-logo.png`
- `/favicon.ico`, `/icon.png`, `/apple-touch-icon.png`

No CDN configuration required beyond Vercel defaults.

---

## 10. Preview vs production deployments

| Deployment | Use |
|------------|-----|
| **Production** (custom domain) | `staging.paylity.ng` — primary staging URL |
| **Preview** (PR branches) | Optional QA; point preview env vars to staging API or a mock |

For PR previews calling the real staging API, set the same `NEXT_PUBLIC_API_BASE_URL` on Preview environment or accept CORS limitations if API only allows `FRONTEND_URL`.

---

## 11. Routine redeploy

Vercel redeploys automatically on push to the connected branch (usually `main`).

Manual redeploy: **Deployments → … → Redeploy**

After API URL or public env changes:

1. Update env vars in Vercel
2. **Redeploy** (rebuild required for `NEXT_PUBLIC_*`)

---

## 12. Rollback

**Deployments →** select previous successful deployment **→ Promote to Production**

Document the build number (`NEXT_PUBLIC_BUILD_NUMBER`) when signing off smoke tests.

---

## 13. Troubleshooting

| Issue | Check |
|-------|-------|
| Build fails | Run `npm run build` locally in `apps/web` |
| API calls go to localhost | `NEXT_PUBLIC_API_BASE_URL` not set; redeploy |
| CORS error | API `FRONTEND_URL=https://staging.paylity.ng` |
| Domain not verifying | CNAME in cPanel matches Vercel exactly |
| Old version in footer | Rebuild after changing `NEXT_PUBLIC_APP_VERSION` |
| Paystack callback 404 | Route `/payment/callback` exists; check deployment logs |

---

## Related docs

- [HYBRID-STAGING-DEPLOYMENT.md](./HYBRID-STAGING-DEPLOYMENT.md)
- [CPANEL-LARAVEL-API-DEPLOYMENT.md](./CPANEL-LARAVEL-API-DEPLOYMENT.md)
- [STAGING-ENV-TEMPLATE.md](./STAGING-ENV-TEMPLATE.md)
- [STAGING-SMOKE-TESTS.md](./STAGING-SMOKE-TESTS.md)
