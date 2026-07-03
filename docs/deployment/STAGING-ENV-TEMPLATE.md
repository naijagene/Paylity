# Staging Environment Templates — PAYLITY NG RC1

Use these templates when provisioning **staging** on the **hybrid stack** (Vercel frontend + cPanel API). Copy values into Vercel env settings and the cPanel API `.env`. Do **not** commit real credentials.

**Deployment guides**

- [HYBRID-STAGING-DEPLOYMENT.md](./HYBRID-STAGING-DEPLOYMENT.md) — overview
- [CPANEL-LARAVEL-API-DEPLOYMENT.md](./CPANEL-LARAVEL-API-DEPLOYMENT.md) — backend on cPanel VPS
- [VERCEL-FRONTEND-DEPLOYMENT.md](./VERCEL-FRONTEND-DEPLOYMENT.md) — frontend on Vercel

---

## Backend — `.env` on cPanel VPS

File location: `/home/<user>/paylity/apps/api/.env`

```env
APP_NAME=PAYLITY NG
APP_ENV=staging
APP_KEY=base64:GENERATE_WITH_php_artisan_key_generate
APP_DEBUG=false
APP_URL=https://api-staging.paylity.ng
FRONTEND_URL=https://staging.paylity.ng
APP_VERSION=1.0.0-rc1
APP_BUILD=2026.07.03-rc1

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paylity_staging
DB_USERNAME=paylity_staging
DB_PASSWORD=CHANGE_ME

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_DOMAIN=.paylity.ng

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS=support@paylity.ng
MAIL_FROM_NAME="${APP_NAME}"

# Paystack — test keys on staging
PAYSTACK_PUBLIC_KEY=pk_test_CHANGE_ME
PAYSTACK_SECRET_KEY=sk_test_CHANGE_ME
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_CALLBACK_URL=https://staging.paylity.ng/payment/callback
FEATURE_PAYSTACK=true

# VTPass — sandbox on staging
VTPASS_BASE_URL=https://sandbox.vtpass.com
VTPASS_USERNAME=CHANGE_ME
VTPASS_PASSWORD=CHANGE_ME
VTPASS_API_KEY=CHANGE_ME
VTPASS_PUBLIC_KEY=
VTPASS_SECRET_KEY=
VTPASS_TIMEOUT=30
VTPASS_RETRY_TIMES=2
VTPASS_RETRY_SLEEP_MS=500
FEATURE_VTPASS=true
FEATURE_VTPASS_AUTO_FULFILL=false

# Ops console
OPERATOR_ACCESS_KEY=CHANGE_ME_STRONG_RANDOM_KEY

# Optional sandbox test helpers (engineering only)
VTPASS_SANDBOX_TESTS=false
VTPASS_SKIP_DATA_CERTIFICATION=false
```

### Backend notes (cPanel)

| Variable | Staging guidance |
|----------|------------------|
| `APP_DEBUG` | Must be `false` |
| `DB_HOST` | Usually `127.0.0.1` on cPanel; use full cPanel-prefixed DB name/user |
| `FEATURE_VTPASS_AUTO_FULFILL` | Keep `false` unless running intentional auto-delivery tests |
| `PAYSTACK_CALLBACK_URL` | Frontend URL on Vercel — not the API domain |
| `FRONTEND_URL` | Must be `https://staging.paylity.ng` for CORS |
| `OPERATOR_ACCESS_KEY` | Required for ops console; rotate if leaked |
| `QUEUE_CONNECTION` | Use `database`; configure cron worker on cPanel |

After configuring (SSH on VPS):

```bash
cd /home/<user>/paylity/apps/api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan paylity:preflight
```

---

## Frontend — Vercel environment variables

Set in **Vercel → Project → Settings → Environment Variables** (Production scope for `staging.paylity.ng`).

```env
NEXT_PUBLIC_API_BASE_URL=https://api-staging.paylity.ng/api/v1
NEXT_PUBLIC_OPERATOR_API_BASE_URL=https://api-staging.paylity.ng/api/v1
NEXT_PUBLIC_SITE_URL=https://staging.paylity.ng
NEXT_PUBLIC_APP_NAME=PAYLITY NG
NEXT_PUBLIC_APP_VERSION=1.0.0-rc1
NEXT_PUBLIC_BUILD_NUMBER=2026.07.03-rc1
NEXT_PUBLIC_BUILD_DATE=2026-07-03
NEXT_PUBLIC_ENVIRONMENT=Staging
NEXT_PUBLIC_GIT_COMMIT=
NEXT_PUBLIC_SUPPORT_EMAIL=support@paylity.ng
NEXT_PUBLIC_WHATSAPP_URL=https://wa.me/234XXXXXXXXXX
```

### Frontend notes (Vercel)

| Variable | Staging guidance |
|----------|------------------|
| `NEXT_PUBLIC_SITE_URL` | SEO metadata base URL |
| `NEXT_PUBLIC_ENVIRONMENT` | Footer/build panel shows **Staging** |
| `NEXT_PUBLIC_WHATSAPP_URL` | Leave empty for “Coming Soon” card; no placeholder numbers |
| Build | Redeploy after changing any `NEXT_PUBLIC_*` value |

---

## Paystack dashboard (staging / test mode)

| Setting | Value |
|---------|-------|
| Callback URL | `https://staging.paylity.ng/payment/callback` |
| Webhook URL | `https://api-staging.paylity.ng/api/v1/payments/paystack/webhook` |

Webhook is handled by Laravel on cPanel. Callback lands on Vercel frontend.

---

## VTPass (staging / sandbox)

| Setting | Value |
|---------|-------|
| Base URL | `https://sandbox.vtpass.com` |
| Credentials | Sandbox username / password / API key in API `.env` only |
| Auto-fulfill | `FEATURE_VTPASS_AUTO_FULFILL=false` initially |

---

## Related docs

- [STAGING-DEPLOYMENT-CHECKLIST.md](./STAGING-DEPLOYMENT-CHECKLIST.md)
- [STAGING-SMOKE-TESTS.md](./STAGING-SMOKE-TESTS.md)
- [../release/PAYLITY-RC1-READINESS-REPORT.md](../release/PAYLITY-RC1-READINESS-REPORT.md)
