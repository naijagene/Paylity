# Server Operations

## Recommended production layout

| Component | Host | Notes |
|-----------|------|-------|
| Laravel API | VPS / cPanel PHP host | PHP 8.2+, Composer, cron |
| Customer frontend | Vercel or static host | `apps/web` |
| Ops console | Separate host or path | `apps/ops`, port 3001 in dev |
| Database | Managed MySQL / MariaDB | Daily backups |
| Queue worker | API host | Supervisor-managed |

## Process management

### PHP-FPM / web server

- Restart after deploy: `sudo systemctl reload php8.2-fpm` (adjust version)
- Ensure `public/` is the web root
- Enforce HTTPS at the reverse proxy

### Scheduler

Add Laravel scheduler cron on the API host:

```cron
* * * * * cd /path/to/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

### Queue worker (systemd example)

```ini
[Unit]
Description=PAYLITY Queue Worker
After=network.target

[Service]
User=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/apps/api/artisan queue:work --sleep=3 --tries=3

[Install]
WantedBy=multi-user.target
```

## Security

- API responses include CSP, HSTS, X-Frame-Options, Referrer-Policy, Permissions-Policy, and X-Content-Type-Options via `SecurityHeadersMiddleware`
- Session cookies: `secure=true`, `http_only=true`, `same_site=lax`
- Rate limits: checkout, OTP, health, catalog, ops, webhooks

## Compression

API JSON responses above 1 KB are gzip-compressed when clients send `Accept-Encoding: gzip`.

## Logs

- Application: `storage/logs/laravel.log`
- Web server and PHP-FPM error logs on the host
- Monitor for 429 rate-limit spikes and 503 health responses

## Incident response

1. Enable incident mode in ops console.
2. Confirm customer banner and checkout block.
3. Review health endpoint and queue metrics.
4. Communicate status to stakeholders.
5. Disable incident mode after recovery.
