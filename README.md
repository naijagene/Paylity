# PAYLITY NG

Fast utility payment platform for Nigeria.

## Local development

### Terminal 1 — API

```bash
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Add Paystack credentials to `apps/api/.env`:

```env
PAYSTACK_SECRET_KEY=sk_test_...
PAYSTACK_PUBLIC_KEY=pk_test_...
PAYSTACK_CALLBACK_URL=http://localhost:3000/payment/callback
FEATURE_PAYSTACK=true
```

### Terminal 2 — Web

```bash
cd apps/web
npm install
cp .env.local.example .env.local
npm run dev
```

Set in `apps/web/.env.local`:

```env
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

- Web: [http://localhost:3000](http://localhost:3000)
- API: [http://127.0.0.1:8000/api/v1/health](http://127.0.0.1:8000/api/v1/health)

## Docs

- `docs/PAY-003-CHECKOUT-EXPERIENCE-BLUEPRINT.md`
- `docs/PAY-005-TRANSACTION-ENGINE-FOUNDATION.md`
