<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Fulfillment\FulfillmentPayloadExtractor;
use App\Services\Marketing\LaunchVoucherService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ReceiptService
{
    private const DISPLAY_TIMEZONE = 'Africa/Lagos';

    public function __construct(
        private readonly FulfillmentPayloadExtractor $fulfillmentPayloadExtractor,
        private readonly LaunchVoucherService $launchVoucherService,
    ) {
    }

    public function ensureVerificationToken(Transaction $transaction): Transaction
    {
        if ($transaction->receipt_verification_token) {
            return $transaction;
        }

        $transaction->update([
            'receipt_verification_token' => Str::lower(Str::random(32)),
        ]);

        return $transaction->fresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildReceiptPayload(Transaction $transaction): ?array
    {
        if (! $this->isReceiptAvailable($transaction)) {
            return null;
        }

        $transaction = $this->ensureVerificationToken($transaction);

        $resolvedPhone = $this->resolvePhone($transaction);
        $maskedPhone = $this->maskPhone($resolvedPhone);
        $productDisplayName = $this->buildProductDisplayName($transaction);
        $timestamp = $this->resolveTimestamp($transaction);

        return [
            'brand' => 'PAYLITY NG',
            'reference' => $transaction->reference,
            'product_type' => $transaction->product_type,
            'product_label' => $productDisplayName,
            'product_display_name' => $productDisplayName,
            'customer_phone' => $resolvedPhone,
            'customer_phone_masked' => $maskedPhone,
            'recipient_phone' => $resolvedPhone,
            'recipient_phone_masked' => $maskedPhone,
            'phone_display' => $maskedPhone,
            'customer_email' => $transaction->customer_email,
            'product_amount' => $transaction->product_amount,
            'voucher_discount_amount' => (int) ($transaction->voucher_discount_amount ?? 0),
            'voucher_code_masked' => $this->launchVoucherService->maskCode($transaction->voucher_code),
            'net_product_amount' => max(0, (int) $transaction->product_amount - (int) ($transaction->voucher_discount_amount ?? 0)),
            'convenience_fee' => $transaction->convenience_fee,
            'gateway_fee' => $transaction->gateway_fee,
            'payable_amount' => $transaction->payable_amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'payment_status' => $this->paymentStatusLabel($transaction),
            'fulfillment_status' => $this->fulfillmentStatusLabel($transaction),
            'failure_reason' => $transaction->failure_reason,
            'fulfillment_reference' => $transaction->fulfillment_reference,
            'timestamp' => $timestamp?->toIso8601String(),
            'timestamp_display' => $this->formatTimestampDisplay($timestamp),
            'verification_token' => $transaction->receipt_verification_token,
            'verification_url' => $this->verificationUrl($transaction),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPublicVerificationPayload(Transaction $transaction): array
    {
        $resolvedPhone = $this->resolvePhone($transaction);
        $productDisplayName = $this->buildProductDisplayName($transaction);
        $timestamp = $this->resolveTimestamp($transaction);

        return [
            'authentic' => true,
            'reference' => $transaction->reference,
            'product_type' => $transaction->product_type,
            'product_label' => $productDisplayName,
            'product_display_name' => $productDisplayName,
            'customer_phone_masked' => $this->maskPhone($resolvedPhone),
            'phone_display' => $this->maskPhone($resolvedPhone),
            'payable_amount' => $transaction->payable_amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'payment_status' => $this->paymentStatusLabel($transaction),
            'fulfillment_status' => $this->fulfillmentStatusLabel($transaction),
            'fulfillment_reference' => $transaction->fulfillment_reference,
            'timestamp' => $timestamp?->toIso8601String(),
            'timestamp_display' => $this->formatTimestampDisplay($timestamp),
            'verified_at' => now()->toIso8601String(),
        ];
    }

    public function verificationUrl(Transaction $transaction): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $token = $transaction->receipt_verification_token
            ?: $this->ensureVerificationToken($transaction)->receipt_verification_token;

        return $frontendUrl.'/verify/'.$token;
    }

    public function isReceiptAvailable(Transaction $transaction): bool
    {
        return in_array($transaction->status, [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
            TransactionStatus::FAILED,
        ], true);
    }

    public function resolvePhone(Transaction $transaction): ?string
    {
        /** @var array<string, mixed> $request */
        $request = (array) ($transaction->request_payload ?? []);
        $fulfillmentDetails = $this->fulfillmentPayloadExtractor->extractPublicDetails($transaction) ?? [];

        $candidates = [
            data_get($transaction, 'recipient_phone'),
            $transaction->customer_phone,
            $request['recipient_phone'] ?? null,
            $request['phone'] ?? null,
            $request['billersCode'] ?? null,
            $request['billers_code'] ?? null,
            data_get($transaction->response_payload, 'customer.phone'),
            data_get($transaction->response_payload, 'verify.data.customer.phone'),
            $fulfillmentDetails['phone'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }

            if (is_numeric($candidate) && (string) $candidate !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }

    public function buildProductDisplayName(Transaction $transaction): string
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) ($transaction->request_payload ?? []);

        return match ($transaction->product_type) {
            'airtime' => $this->airtimeDisplayName($payload),
            'data' => $this->dataDisplayName($payload),
            'electricity' => $this->electricityDisplayName($payload),
            default => $this->productLabel($transaction->product_type),
        };
    }

    public function maskPhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '—';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '—';
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 0, 4).' XXX '.substr($digits, 7);
        }

        if (strlen($digits) >= 7) {
            return substr($digits, 0, 4).' XXX '.substr($digits, -4);
        }

        return '—';
    }

    public function resolveTimestamp(Transaction $transaction): ?Carbon
    {
        $paidAt = data_get($transaction->response_payload, 'verify.data.paid_at')
            ?? data_get($transaction->response_payload, 'verify.paid_at');

        if (is_string($paidAt) && $paidAt !== '') {
            return Carbon::parse($paidAt);
        }

        if ($transaction->fulfilled_at !== null) {
            return $transaction->fulfilled_at;
        }

        return $transaction->created_at;
    }

    public function formatTimestampDisplay(?Carbon $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        $timezone = (string) config('app.display_timezone', self::DISPLAY_TIMEZONE);

        return $timestamp
            ->copy()
            ->timezone($timezone)
            ->format('d M Y, h:i A').' WAT';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function airtimeDisplayName(array $payload): string
    {
        $network = (string) ($payload['network'] ?? '');

        if ($network === '') {
            return $this->productLabel('airtime');
        }

        return $this->formatNetworkLabel($network).' Airtime';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dataDisplayName(array $payload): string
    {
        $network = (string) ($payload['network'] ?? '');
        $planName = (string) (
            $payload['display_name']
            ?? $payload['plan_name']
            ?? $payload['provider_variation_name']
            ?? ''
        );

        if ($network !== '' && $planName !== '') {
            return $this->formatNetworkLabel($network).' '.$planName;
        }

        if ($planName !== '') {
            return $planName;
        }

        return $this->productLabel('data');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function electricityDisplayName(array $payload): string
    {
        $disco = (string) ($payload['disco'] ?? '');
        $meterType = (string) ($payload['meter_type'] ?? $payload['meterType'] ?? '');

        if ($disco === '') {
            return $this->productLabel('electricity');
        }

        $label = strtoupper($disco);

        if ($meterType !== '') {
            $label .= ' '.ucfirst(strtolower($meterType));
        }

        return $label.' Electricity';
    }

    private function formatNetworkLabel(string $network): string
    {
        return match (strtolower(trim($network))) {
            'mtn' => 'MTN',
            'airtel' => 'Airtel',
            'glo' => 'Glo',
            '9mobile', 'etisalat' => '9Mobile',
            default => strtoupper($network),
        };
    }

    private function productLabel(string $productType): string
    {
        return match ($productType) {
            'airtime' => 'Airtime',
            'data' => 'Data',
            'electricity' => 'Electricity',
            default => ucfirst($productType),
        };
    }

    private function paymentStatusLabel(Transaction $transaction): string
    {
        return match ($transaction->status) {
            TransactionStatus::PAYMENT_FAILED => 'Payment Failed',
            TransactionStatus::PAYMENT_PENDING => 'Payment Pending',
            default => 'Payment Successful',
        };
    }

    private function fulfillmentStatusLabel(Transaction $transaction): string
    {
        return match ($transaction->status) {
            TransactionStatus::FULFILLED => 'Delivered',
            TransactionStatus::FULFILLMENT_PENDING => 'Processing',
            TransactionStatus::FAILED => 'Delivery Failed',
            TransactionStatus::PAYMENT_SUCCESS => 'Awaiting Delivery',
            default => 'Not Started',
        };
    }
}
