<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAYLITY Receipt — {{ $receipt['reference'] }}</title>
    <style>
        :root {
            --background: #f8fafc;
            --foreground: #0f172a;
            --muted: #64748b;
            --card: #ffffff;
            --border: #e5e7eb;
            --border-green: #d1fae5;
            --success: #10b981;
            --success-light: #ecfdf5;
            --error: #dc2626;
            --amber-700: #b45309;
            --amber-50: #fffbeb;
            --amber-200: #fde68a;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 16px;
            background: var(--background);
            color: var(--foreground);
            font-family: "Segoe UI", Inter, Arial, sans-serif;
            line-height: 1.5;
        }

        .page {
            max-width: 720px;
            margin: 0 auto;
        }

        .receipt-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 24px 28px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08), 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .receipt-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.05);
            padding-bottom: 20px;
            margin-bottom: 22px;
        }

        .brand-lockup {
            font-size: 22px;
            font-weight: 800;
            color: #0b2d5c;
            letter-spacing: -0.02em;
        }

        .brand-accent {
            color: var(--success);
        }

        .receipt-kicker {
            margin-top: 16px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(15, 23, 42, 0.45);
        }

        .product-name {
            margin: 8px 0 0;
            font-size: 24px;
            font-weight: 800;
            color: var(--foreground);
            line-height: 1.2;
        }

        .reference {
            margin: 12px 0 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 14px;
            font-weight: 700;
            color: var(--foreground);
        }

        .timestamp {
            margin: 8px 0 0;
            font-size: 14px;
            color: var(--muted);
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
            align-content: flex-start;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-color: rgba(16, 185, 129, 0.2);
        }

        .badge-processing {
            background: var(--amber-50);
            color: var(--amber-700);
            border-color: var(--amber-200);
        }

        .badge-info {
            background: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }

        .badge-failed {
            background: rgba(220, 38, 38, 0.1);
            color: var(--error);
            border-color: rgba(220, 38, 38, 0.2);
        }

        .section {
            margin-bottom: 22px;
        }

        .section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            margin: 0 0 12px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(15, 23, 42, 0.45);
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 8px 0;
        }

        .row-label {
            font-size: 14px;
            color: rgba(15, 23, 42, 0.6);
        }

        .row-value {
            max-width: 58%;
            text-align: right;
            font-size: 14px;
            font-weight: 600;
            color: var(--foreground);
        }

        .charges-box {
            background: rgba(15, 23, 42, 0.02);
            border-radius: 16px;
            padding: 16px 20px;
        }

        .charges-divider {
            border-top: 1px solid rgba(15, 23, 42, 0.05);
            margin: 12px 0;
        }

        .row-emphasis .row-value {
            font-size: 16px;
            font-weight: 800;
        }

        .verification {
            text-align: center;
            padding: 8px 0 4px;
        }

        .verification img {
            display: block;
            margin: 0 auto;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            padding: 10px;
        }

        .verify-hint {
            margin-top: 14px;
            font-size: 13px;
            color: var(--muted);
        }

        .footer {
            margin-top: 22px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--muted);
            text-align: center;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .page {
                max-width: none;
            }

            .receipt-card {
                box-shadow: none;
                border: 1px solid var(--border);
                border-radius: 0;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <article class="receipt-card" aria-label="Transaction receipt">
            <header class="receipt-header">
                <div>
                    <div class="brand-lockup">PAYLITY <span class="brand-accent">NG</span></div>
                    <p class="receipt-kicker">Receipt</p>
                    <h1 class="product-name">{{ $receipt['product_display_name'] ?? $receipt['product_label'] }}</h1>
                    <p class="reference">{{ $receipt['reference'] }}</p>
                    @if(!empty($receipt['timestamp_display']))
                        <p class="timestamp">{{ $receipt['timestamp_display'] }}</p>
                    @endif
                </div>
                <div class="badges">
                    <span class="badge badge-{{ $paymentBadgeVariant }}">{{ $paymentBadgeLabel }}</span>
                    <span class="badge badge-{{ $fulfillmentBadgeVariant }}">{{ $fulfillmentBadgeLabel }}</span>
                </div>
            </header>

            <section class="section">
                <h2 class="section-title">Customer</h2>
                <div class="row">
                    <span class="row-label">Phone</span>
                    <span class="row-value">{{ $receipt['phone_display'] ?? $receipt['customer_phone_masked'] ?? '—' }}</span>
                </div>
                @if(!empty($receipt['customer_email']))
                <div class="row">
                    <span class="row-label">Email</span>
                    <span class="row-value">{{ $receipt['customer_email'] }}</span>
                </div>
                @endif
            </section>

            <section class="section">
                <h2 class="section-title">Charges</h2>
                <div class="charges-box">
                    <div class="row">
                        <span class="row-label">Product Amount</span>
                        <span class="row-value">₦{{ number_format($receipt['product_amount'], 2) }}</span>
                    </div>
                    <div class="row">
                        <span class="row-label">Convenience Fee</span>
                        <span class="row-value">₦{{ number_format($receipt['convenience_fee'], 2) }}</span>
                    </div>
                    <div class="row">
                        <span class="row-label">Payment Processing Fee</span>
                        <span class="row-value">₦{{ number_format($receipt['gateway_fee'], 2) }}</span>
                    </div>
                    <div class="charges-divider"></div>
                    <div class="row row-emphasis">
                        <span class="row-label">Total Paid</span>
                        <span class="row-value">₦{{ number_format($receipt['payable_amount'], 2) }}</span>
                    </div>
                </div>
            </section>

            <section class="section">
                <h2 class="section-title">Status</h2>
                <div class="row">
                    <span class="row-label">Payment</span>
                    <span class="row-value">{{ $receipt['payment_status'] }}</span>
                </div>
                <div class="row">
                    <span class="row-label">Fulfillment</span>
                    <span class="row-value">{{ $receipt['fulfillment_status'] }}</span>
                </div>
                @if(!empty($receipt['failure_reason']))
                <div class="row">
                    <span class="row-label">Failure Reason</span>
                    <span class="row-value">{{ $receipt['failure_reason'] }}</span>
                </div>
                @endif
            </section>

            <section class="section">
                <h2 class="section-title">Verify Receipt Authenticity</h2>
                <div class="verification">
                    <img src="{{ $qrCodeDataUri }}" alt="Verification QR code" width="120" height="120">
                    <div class="verify-hint">Scan QR or copy verification link</div>
                </div>
            </section>

            <footer class="footer">
                Generated by PAYLITY NG. Verify authenticity using the QR code or verification link.
            </footer>
        </article>
    </div>
</body>
</html>
