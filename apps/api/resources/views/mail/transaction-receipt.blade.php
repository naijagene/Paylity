PAYLITY NG — Transaction Receipt
================================

Reference: {{ $transaction->reference }}
Product: {{ $receipt['product_display_name'] ?? $receipt['product_label'] ?? $transaction->product_type }}
Phone: {{ $receipt['phone_display'] ?? $receipt['customer_phone_masked'] ?? '—' }}
@if(!empty($receipt['customer_email']))
Email: {{ $receipt['customer_email'] }}
@endif
@if(!empty($receipt['timestamp_display']))
Timestamp: {{ $receipt['timestamp_display'] }}
@endif

Product Amount: ₦{{ number_format($receipt['product_amount'] ?? $transaction->product_amount, 2) }}
Convenience Fee: ₦{{ number_format($receipt['convenience_fee'] ?? $transaction->convenience_fee, 2) }}
Total Paid: ₦{{ number_format($receipt['payable_amount'] ?? $transaction->payable_amount, 2) }}

Payment: {{ $receipt['payment_status'] ?? 'Payment Successful' }}
Fulfillment: {{ $receipt['fulfillment_status'] ?? 'Awaiting Delivery' }}

Verify this receipt:
{{ $receipt['verification_url'] ?? '' }}

Thank you for using PAYLITY NG.
