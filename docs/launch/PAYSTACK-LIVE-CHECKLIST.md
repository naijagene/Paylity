# Paystack Live Checklist

- Live public/secret keys in production `.env`
- Callback: `https://<customer-domain>/payment/callback`
- Webhook: `https://<api-domain>/api/v1/payments/paystack/webhook`
- Run `php artisan paylity:paystack-mode`
