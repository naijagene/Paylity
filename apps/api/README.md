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
- Paystack and VTPass are not integrated yet.
