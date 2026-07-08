# Production Environment Reference

## Required API variables

| Variable | Purpose |
|----------|---------|
| `APP_ENV` | Must be `production` |
| `APP_DEBUG` | Must be `false` |
| `APP_URL` | Public API base URL |
| `FRONTEND_URL` | Customer frontend origin for CORS |
| `APP_VERSION` | Release version label |
| `APP_BUILD` | Build identifier |
| `APP_KEY` | Laravel encryption key |
| `OPERATOR_ACCESS_KEY` | Ops console authentication |
| `DB_*` | Database connection |
| `SESSION_SECURE_COOKIE` | `true` in production |
| `QUEUE_CONNECTION` | `database` or `redis` recommended |
| `MAIL_MAILER` | Production mail transport |

## Payment and fulfillment

| Variable | Purpose |
|----------|---------|
| `FEATURE_PAYSTACK` | Enable Paystack collection |
| `PAYSTACK_PUBLIC_KEY` | Client-side key |
| `PAYSTACK_SECRET_KEY` | Server-side key |
| `PAYSTACK_CALLBACK_URL` | Payment return URL |
| `FEATURE_VTPASS` | Enable VTPass fulfillment |
| `VTPASS_USERNAME` | VTPass API username |
| `VTPASS_PASSWORD` | VTPass API password |
| `VTPASS_API_KEY` | VTPass API key |
| `FEATURE_VTPASS_AUTO_FULFILL` | Auto-fulfill after payment |

## Frontend variables

| App | Variable | Purpose |
|-----|----------|---------|
| `apps/web` | `NEXT_PUBLIC_API_BASE_URL` | Customer API base |
| `apps/web` | `NEXT_PUBLIC_SITE_URL` | Canonical site URL |
| `apps/ops` | `NEXT_PUBLIC_OPERATOR_API_BASE_URL` | Ops API base |

## Startup validation

Run before every production deploy:

```bash
php artisan paylity:preflight
```

`PaylityEnvironmentValidator` checks environment, secrets, CORS, queue, logging, session cookies, and mail configuration.

## System settings (runtime)

Managed via ops console or `/api/v1/settings`:

- `maintenance_mode` — disables checkout
- `incident_mode` — disables checkout and shows customer incident banner
- `guest_checkout_enabled`, OTP thresholds, daily limits

## Health endpoints

- `GET /api/v1/health` — API, database, cache, queue, mail, Paystack, VTPass, version
- `GET /api/v1/platform/status` — checkout availability and incident/maintenance state
