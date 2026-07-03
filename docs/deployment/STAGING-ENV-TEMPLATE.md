# Staging Environment Templates — PAYLITY NG RC1

Use these templates when provisioning **staging**. Copy values into your deployment secrets manager or server `.env` files. Do **not** commit real credentials.

---

## Backend — `.env.staging`

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

### Backend notes

| Variable | Staging guidance |
|----------|------------------|
| `APP_DEBUG` | Must be `false` |
| `FEATURE_VTPASS_AUTO_FULFILL` | Keep `false` unless running intentional auto-delivery tests |
| `PAYSTACK_CALLBACK_URL` | Must match the **frontend** callback route on staging |
| `OPERATOR_ACCESS_KEY` | Required for ops console; rotate if leaked |
| `QUEUE_CONNECTION` | Use `database` or `redis`; run a queue worker |
| `LOG_LEVEL` | `info` or `warning` on staging |

After configuring:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan paylity:preflight
```

---

## Frontend — `.env.staging`

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

### Frontend notes

| Variable | Staging guidance |
|----------|------------------|
| `NEXT_PUBLIC_SITE_URL` | Used for SEO metadata base URL |
| `NEXT_PUBLIC_ENVIRONMENT` | Displayed in footer/build panel as **Staging** |
| `NEXT_PUBLIC_WHATSAPP_URL` | Omit placeholder numbers; leave empty to show “Coming Soon” card |
| Build | Run `npm run build` with these vars injected at build time |

---

## Paystack dashboard (staging)

Configure in Paystack test mode:

| Setting | Value |
|---------|-------|
| Callback URL | `https://staging.paylity.ng/payment/callback` |
| Webhook URL | `https://api-staging.paylity.ng/api/v1/payments/paystack/webhook` |

Confirm webhook secret matches `PAYSTACK_SECRET_KEY` verification in Laravel.

---

## Related docs

- [STAGING-DEPLOYMENT-CHECKLIST.md](./STAGING-DEPLOYMENT-CHECKLIST.md)
- [STAGING-SMOKE-TESTS.md](./STAGING-SMOKE-TESTS.md)
- [../release/PAYLITY-RC1-READINESS-REPORT.md](../release/PAYLITY-RC1-READINESS-REPORT.md)
