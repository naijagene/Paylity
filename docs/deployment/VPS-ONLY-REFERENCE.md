# VPS-Only Reference (Optional)

**Not required for PAYLITY staging.**

Hybrid staging uses **Vercel (frontend) + cPanel (API)**. See [HYBRID-STAGING-DEPLOYMENT.md](./HYBRID-STAGING-DEPLOYMENT.md).

Use this document only if you deploy the API or frontend on a **raw Linux VPS** with Nginx and systemd instead of cPanel/Vercel.

---

## When to use this path

| Scenario | Recommended path |
|----------|------------------|
| Staging RC1 | Hybrid (Vercel + cPanel) |
| API on dedicated VPS with Nginx | This reference |
| Frontend self-hosted on same VPS | This reference + PM2/systemd |
| Production at scale | Managed platform or tuned Nginx stack |

---

## Nginx — Laravel API

```nginx
server {
    listen 443 ssl http2;
    server_name api-staging.paylity.ng;

    root /var/www/paylity/apps/api/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/paylity-api.crt;
    ssl_certificate_key /etc/ssl/private/paylity-api.key;

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

---

## Nginx — Next.js reverse proxy (self-hosted frontend)

Only if **not** using Vercel:

```nginx
server {
    listen 443 ssl http2;
    server_name staging.paylity.ng;

    ssl_certificate     /etc/ssl/certs/paylity-web.crt;
    ssl_certificate_key /etc/ssl/private/paylity-web.key;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

Build and run Next.js:

```bash
cd apps/web
npm ci && npm run build
npm run start   # port 3000 — use PM2 or systemd below
```

---

## systemd — Laravel queue worker

`/etc/systemd/system/paylity-queue.service`:

```ini
[Unit]
Description=PAYLITY Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /var/www/paylity/apps/api/artisan queue:work --sleep=3 --tries=3 --max-time=3600
WorkingDirectory=/var/www/paylity/apps/api

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable paylity-queue
sudo systemctl start paylity-queue
```

---

## systemd — Next.js (self-hosted)

`/etc/systemd/system/paylity-web.service`:

```ini
[Unit]
Description=PAYLITY Next.js Frontend
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/paylity/apps/web
Environment=NODE_ENV=production
Environment=PORT=3000
ExecStart=/usr/bin/npm run start

[Install]
WantedBy=multi-user.target
```

Prefer **Vercel** for staging frontend instead of maintaining this unit.

---

## Cron — Laravel scheduler

```cron
* * * * * cd /var/www/paylity/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

---

## Related docs

- [HYBRID-STAGING-DEPLOYMENT.md](./HYBRID-STAGING-DEPLOYMENT.md) — **primary staging path**
- [../launch/DEPLOYMENT-GUIDE.md](../launch/DEPLOYMENT-GUIDE.md) — general production guide
