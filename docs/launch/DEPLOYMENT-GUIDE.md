# PAYLITY NG — Deployment Guide

Step-by-step guide for staging and production deployment.

> **Staging (RC1):** Use the **hybrid path** — Vercel frontend + cPanel API.  
> Start here: [docs/deployment/HYBRID-STAGING-DEPLOYMENT.md](../deployment/HYBRID-STAGING-DEPLOYMENT.md)

---

## Production server requirements

**Minimum (soft launch)**

| Resource | Spec |
|----------|------|
| API server | 1 vCPU, 2 GB RAM |
| Web server | 1 vCPU, 1 GB RAM (or static host) |
| Database | Managed PostgreSQL or MySQL |

**Recommended (public launch)**

| Resource | Spec |
|----------|------|
| API | 2 vCPU, 4 GB RAM |
| Web | CDN + Node host or Vercel |
| DB | Managed PostgreSQL with daily backups |
| SSL | Required on all public URLs |

---

## PHP / Laravel requirements

- PHP **8.2+**
- Extensions: `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- Composer 2.x
- Laravel **12.x** (current: `apps/api`)

---

## Node / Next.js requirements

- Node.js **20+**
- npm
- Next.js **16.x** (current: `apps/web`)

---

## Database recommendation

| Environment | Recommendation |
|-------------|------------------|
| Local dev | SQLite (current default) |
| Staging / Production | **PostgreSQL** (preferred) or MySQL 8 |

SQLite is not suitable for concurrent production traffic.

**Migration steps (PostgreSQL example)**

```bash
cd apps/api
# Update .env
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=paylity
DB_USERNAME=...
DB_PASSWORD=...

php artisan migrate --force
```

---

## Web server (Nginx)

> **Optional — not used for hybrid staging.** Staging frontend is on Vercel; staging API is on cPanel (Apache/LiteSpeed).  
> For raw VPS deployments, see [docs/deployment/VPS-ONLY-REFERENCE.md](../deployment/VPS-ONLY-REFERENCE.md).

Example API upstream:

```nginx
server {
    listen 443 ssl http2;
    server_name api.paylity.ng;

    root /var/www/paylity/apps/api/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/paylity.crt;
    ssl_certificate_key /etc/ssl/private/paylity.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Example web (proxy to Next.js):

```nginx
server {
    listen 443 ssl http2;
    server_name paylity.ng www.paylity.ng;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

Apache can use `mod_proxy` similarly; Nginx is recommended for simplicity.

---

## SSL requirement

- **Mandatory** for production
- Paystack live mode requires HTTPS callback URL
- Use Let's Encrypt or cloud provider certificates
- Force HTTPS redirects on both web and API domains

---

## Environment variables

### Backend (`apps/api/.env`)

```env
APP_NAME=PAYLITY NG
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.paylity.ng
FRONTEND_URL=https://paylity.ng
APP_VERSION=1.0.0
APP_BUILD=2026.07.03

DB_CONNECTION=pgsql
DB_HOST=...
DB_DATABASE=paylity
DB_USERNAME=...
DB_PASSWORD=...

PAYSTACK_PUBLIC_KEY=pk_live_...
PAYSTACK_SECRET_KEY=sk_live_...
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_CALLBACK_URL=https://paylity.ng/payment/callback
FEATURE_PAYSTACK=true

VTPASS_BASE_URL=https://vtpass.com
VTPASS_USERNAME=...
VTPASS_PASSWORD=...
VTPASS_API_KEY=...
FEATURE_VTPASS=true
FEATURE_VTPASS_AUTO_FULFILL=false

OPERATOR_ACCESS_KEY=<strong-random-key>
LOG_LEVEL=warning
```

### Frontend (`apps/web/.env.production` or host env)

```env
NEXT_PUBLIC_API_BASE_URL=https://api.paylity.ng/api/v1
NEXT_PUBLIC_OPERATOR_API_BASE_URL=https://api.paylity.ng/api/v1
NEXT_PUBLIC_APP_NAME=PAYLITY NG
NEXT_PUBLIC_APP_VERSION=1.0.0
NEXT_PUBLIC_BUILD_NUMBER=2026.07.03
NEXT_PUBLIC_BUILD_DATE=2026-07-03
NEXT_PUBLIC_ENVIRONMENT=Production
NEXT_PUBLIC_GIT_COMMIT=<git-sha>
NEXT_PUBLIC_WHATSAPP_URL=https://wa.me/234XXXXXXXXXX?text=...
```

---

## Laravel deployment steps

```bash
cd apps/api

composer install --no-dev --optimize-autoloader
cp .env.example .env   # then edit for production
php artisan key:generate
php artisan migrate --force
php artisan paylity:preflight
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

**Paystack webhook URL (configure in Paystack dashboard)**

```
https://api.paylity.ng/api/v1/payments/paystack/webhook
```

---

## Next.js deployment steps

```bash
cd apps/web

npm ci
npm run build
npm run start   # or deploy to Vercel / similar

# Vercel: set env vars in dashboard, root directory apps/web
```

Ensure `NEXT_PUBLIC_API_BASE_URL` points to production API before build (Next.js bakes public env at build time).

---

## Queue / cron notes

**Current MVP:** No background jobs required for core flow (sync HTTP).

**Recommended for production:**

```cron
* * * * * cd /var/www/paylity/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

Future use: async fulfillment, webhook retries, receipt emails.

`QUEUE_CONNECTION=database` is configured but unused in MVP — enable when adding jobs.

---

## Backup notes

- **Database:** Daily automated snapshots (retain 7–30 days)
- **`.env` files:** Store secrets in vault (not git)
- **Transaction table:** Primary business record — test restore monthly

---

## Smoke test steps (post-deploy)

1. `GET https://api.paylity.ng/api/v1/health` — returns version, build, `status: ok`
2. Open `https://paylity.ng` — landing page loads, footer shows Production build
3. Checkout airtime ₦500 → Paystack live/test → callback success
4. Verify transaction status page shows `payment_success`
5. Manual fulfill via ops console (if VTPass enabled) → status `fulfilled`
6. Trigger invalid reference → friendly 404 on status page
7. Send test Paystack webhook — signature rejected without valid header
8. Confirm CORS: browser checkout calls API without errors

Full checklist: `GO-LIVE-CHECKLIST.md`  
Full sandbox procedure: `../PAY-011-SANDBOX-E2E-TEST.md`

---

*Document: PAY-012 · Deployment Guide*
