# PAYLITY NG Web

Next.js frontend for PAYLITY NG utility payments.

## Requirements

- Node.js 20+
- npm

## Environment

Copy the example env file and adjust if needed:

```bash
cp .env.local.example .env.local
```

`.env.local`:

```env
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

## Local development

Run the backend and frontend in separate terminals.

**Terminal 1 — Laravel API**

```bash
cd apps/api
php artisan serve
```

**Terminal 2 — Next.js web**

```bash
cd apps/web
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000)

Checkout: [http://localhost:3000/checkout?product=airtime](http://localhost:3000/checkout?product=airtime)

## Scripts

```bash
npm run dev
npm run build
npm run lint
```

## Checkout API integration

The checkout review step calls:

`POST /api/v1/checkout/initialize`

When Paystack is enabled on the API, the response includes `authorization_url` and the browser redirects to Paystack checkout.

After payment, Paystack redirects to:

`/payment/callback?reference=...`

The frontend displays backend-confirmed:

- `reference`
- `product_amount`
- `convenience_fee`
- `gateway_fee`
- `payable_amount`
- `authorization_url` (when Paystack is enabled)

VTPass fulfillment is not integrated yet.
