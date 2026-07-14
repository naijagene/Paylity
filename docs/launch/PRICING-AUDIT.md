# Pricing Audit

Negative margin on staging was caused by zero gateway fee at checkout. Fix: pass Paystack fee to customer (Policy A). Run `php artisan paylity:pricing-audit --product=airtime`. All launch amounts must show positive margin before production.

Example ₦1,000 airtime: convenience ₦100, gateway ₦118, payable ₦1,218, margin ~₦100.
