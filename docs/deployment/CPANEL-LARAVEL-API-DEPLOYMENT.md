# cPanel Laravel API Deployment — PAYLITY NG Staging

Deploy the PAYLITY API (`apps/api`) to an existing **cPanel VPS** at:

**`https://api-staging.paylity.ng`**

Part of the hybrid staging path: [HYBRID-STAGING-DEPLOYMENT.md](./HYBRID-STAGING-DEPLOYMENT.md)

---

## Prerequisites

| Requirement | Notes |
|-------------|-------|
| cPanel VPS with SSH | Recommended for Composer and Artisan |
| PHP **8.2+** | Select in cPanel **MultiPHP Manager** |
| Extensions | `openssl`, `pdo_mysql` or `pdo_pgsql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` |
| Composer 2.x | Via SSH or cPanel **Composer** tool if available |
| MySQL 8 or PostgreSQL | Create database in cPanel **MySQL® Databases** or **PostgreSQL Databases** |
| Subdomain | `api-staging.paylity.ng` |

---

## 1. DNS (cPanel Zone Editor)

In **Domains → Zone Editor** for `paylity.ng`:

| Type | Name | Points to |
|------|------|-----------|
| **A** | `api-staging` | `<VPS_PUBLIC_IP>` |

Wait for propagation, then proceed with subdomain setup.

---

## 2. Create subdomain and document root

1. **Domains → Subdomains** (or **Domains → Domains** depending on cPanel version)
2. Create subdomain: `api-staging`
3. Set document root to Laravel **`public`** directory, for example:

   ```
   /home/<cpanel_user>/paylity/apps/api/public
   ```

   **Critical:** The web root must be `public/`, not the Laravel project root.

4. Enable **AutoSSL** / Let’s Encrypt for `api-staging.paylity.ng`

### Directory layout (recommended)

```
/home/<cpanel_user>/paylity/
├── apps/
│   └── api/                 ← Laravel project
│       ├── app/
│       ├── bootstrap/
│       ├── config/
│       ├── public/          ← document root
│       ├── storage/
│       └── ...
```

---

## 3. Deploy application code

Choose one method:

### Option A — Git (recommended)

If cPanel **Git™ Version Control** is available:

1. Clone repository into `/home/<user>/paylity`
2. Branch: `main` or your release branch
3. Post-deploy command (if supported):

   ```bash
   cd /home/<user>/paylity/apps/api && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan optimize
   ```

### Option B — SSH pull

```bash
cd /home/<user>/paylity
git pull origin main
cd apps/api
composer install --no-dev --optimize-autoloader
```

### Option C — SFTP / File Manager

Upload `apps/api` excluding `vendor/`, then run Composer via SSH.

---

## 4. PHP version

1. **Software → MultiPHP Manager**
2. Assign **PHP 8.2** (or 8.3) to `api-staging.paylity.ng`
3. Confirm required extensions are enabled in **MultiPHP INI Editor** or **Select PHP Version → Extensions**

---

## 5. Database (cPanel)

### MySQL (common on cPanel)

1. **Databases → MySQL® Databases**
2. Create database: e.g. `cpaneluser_paylity_staging`
3. Create user with strong password
4. Add user to database with **ALL PRIVILEGES**

Use cPanel’s full database name and username in `.env` (often prefixed with cPanel account name).

### PostgreSQL (if available)

1. **PostgreSQL Databases**
2. Create database + user similarly
3. Set `DB_CONNECTION=pgsql` in `.env`

---

## 6. Environment file

Create `/home/<user>/paylity/apps/api/.env` from [STAGING-ENV-TEMPLATE.md](./STAGING-ENV-TEMPLATE.md).

**Staging-specific values:**

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://api-staging.paylity.ng
FRONTEND_URL=https://staging.paylity.ng

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<cpanel_prefixed_db_name>
DB_USERNAME=<cpanel_prefixed_db_user>
DB_PASSWORD=<strong_password>

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

PAYSTACK_CALLBACK_URL=https://staging.paylity.ng/payment/callback
FEATURE_VTPASS_AUTO_FULFILL=false
```

Protect `.env`: file permissions `600` if possible.

---

## 7. First-time setup commands (SSH)

```bash
cd /home/<user>/paylity/apps/api

# Install dependencies (production)
composer install --no-dev --optimize-autoloader

# Application key (first deploy only — skip if APP_KEY already set)
php artisan key:generate

# Database
php artisan migrate --force

# Cache config/routes/views
php artisan optimize

# Pre-deploy validation
php artisan paylity:preflight
```

Resolve all **FAIL** items before go-live.

---

## 8. File permissions

Laravel must write to `storage/` and `bootstrap/cache/`:

```bash
cd /home/<user>/paylity/apps/api
chmod -R ug+rwx storage bootstrap/cache
```

If the web server user differs from your SSH user, align group ownership per host policy (common on shared cPanel: user owns both).

---

## 9. Scheduler (cron)

**cPanel → Advanced → Cron Jobs**

Add:

```cron
* * * * * /usr/local/bin/php /home/<user>/paylity/apps/api/artisan schedule:run >> /dev/null 2>&1
```

Adjust PHP binary path (`which php` on SSH).

---

## 10. Queue worker (cPanel options)

Set `QUEUE_CONNECTION=database` in `.env` (not `sync`).

### Option A — Cron worker (simplest on cPanel)

Run a worker every minute that processes pending jobs:

```cron
* * * * * /usr/local/bin/php /home/<user>/paylity/apps/api/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

### Option B — Long-running worker (SSH + screen/tmux)

If SSH access allows persistent processes:

```bash
cd /home/<user>/paylity/apps/api
nohup php artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> storage/logs/queue.log 2>&1 &
```

Restart after each deploy.

### Option C — Supervisor (VPS with root)

Only if Supervisor is installed outside cPanel constraints — see [VPS-ONLY-REFERENCE.md](./VPS-ONLY-REFERENCE.md).

---

## 11. `.htaccess` / rewrite

Laravel’s default `public/.htaccess` should handle routing. Confirm **AllowOverride** is enabled (normal on cPanel).

Test:

```bash
curl -s https://api-staging.paylity.ng/api/v1/health
```

Expected: JSON with `"success": true`.

---

## 12. CORS

With `FRONTEND_URL=https://staging.paylity.ng` and `APP_ENV=staging`, the API allows the Vercel frontend origin. Verify after frontend deploy:

```bash
curl -s -I -X OPTIONS \
  -H "Origin: https://staging.paylity.ng" \
  -H "Access-Control-Request-Method: GET" \
  https://api-staging.paylity.ng/api/v1/health
```

---

## 13. Paystack webhook on cPanel

Ensure POST requests reach Laravel (no WAF blocking). Webhook URL:

```
https://api-staging.paylity.ng/api/v1/payments/paystack/webhook
```

Register in Paystack test dashboard. No extra cPanel route needed if document root is `public/`.

---

## 14. VTPass sandbox

```env
VTPASS_BASE_URL=https://sandbox.vtpass.com
FEATURE_VTPASS=true
FEATURE_VTPASS_AUTO_FULFILL=false
```

Test from SSH:

```bash
php artisan paylity:vtpass-check
```

---

## 15. Deploy updates (routine)

```bash
cd /home/<user>/paylity
git pull origin main
cd apps/api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan paylity:preflight
# Restart queue worker if using Option B
```

---

## 16. Troubleshooting

| Issue | Check |
|-------|-------|
| 500 error | `storage/logs/laravel.log`, permissions on `storage/` |
| 404 on all routes | Document root must be `public/` |
| DB connection refused | `DB_HOST=127.0.0.1`, cPanel DB name prefix |
| CORS blocked from Vercel | `FRONTEND_URL`, `APP_ENV=staging` |
| Preflight FAIL on APP_DEBUG | Set `APP_DEBUG=false` |
| Webhook not received | SSL valid, firewall, Paystack URL exact |

---

## Related docs

- [HYBRID-STAGING-DEPLOYMENT.md](./HYBRID-STAGING-DEPLOYMENT.md)
- [VERCEL-FRONTEND-DEPLOYMENT.md](./VERCEL-FRONTEND-DEPLOYMENT.md)
- [STAGING-ENV-TEMPLATE.md](./STAGING-ENV-TEMPLATE.md)
- [STAGING-SMOKE-TESTS.md](./STAGING-SMOKE-TESTS.md)
