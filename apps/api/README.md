# PAYLITY NG API

Laravel 12 API foundation for PAYLITY's Transaction Engine.

## Requirements

- PHP 8.2+
- Composer
- SQLite (local default) or PostgreSQL

## Setup

```bash
cd apps/api
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

### Paystack (optional)

Add to `.env`:

```env
PAYSTACK_PUBLIC_KEY=pk_test_...
PAYSTACK_SECRET_KEY=sk_test_...
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_CALLBACK_URL=http://localhost:3000/payment/callback
FEATURE_PAYSTACK=true
```

When `FEATURE_PAYSTACK=true`, checkout initialization calls Paystack and returns an `authorization_url`. When `false`, the placeholder response is returned instead.

## Run

```bash
php artisan serve
```

API base URL: `http://127.0.0.1:8000/api/v1`

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | Health check |
| POST | `/checkout/initialize` | Initialize checkout transaction |
| GET | `/transactions/{reference}` | Fetch transaction by reference |
| POST | `/payments/paystack/callback` | Paystack callback placeholder |
| GET | `/payments/paystack/verify/{reference}` | Verify payment with Paystack |
| POST | `/payments/paystack/webhook` | Paystack webhook placeholder |

## Example: Initialize checkout

```bash
curl -X POST http://127.0.0.1:8000/api/v1/checkout/initialize \
  -H "Content-Type: application/json" \
  -d '{
    "product_type": "airtime",
    "customer_phone": "08031234567",
    "product_amount": 1000,
    "payload": {}
  }'
```

## Tests

```bash
php artisan test
```

## Notes

- All money values are stored and returned in **naira** (integer).
- Guest checkout limit applies to `product_amount` only (max ₦10,000).
- Convenience fee is ₦100 for all v1 products.
- Paystack initialization is supported when `FEATURE_PAYSTACK=true`.
- VTPass fulfillment is not integrated yet.
