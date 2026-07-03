<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Support\Str;

class ReceiptService
{
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

        return [
            'brand' => 'PAYLITY NG',
            'reference' => $transaction->reference,
            'product_type' => $transaction->product_type,
            'product_label' => $this->productLabel($transaction->product_type),
            'customer_phone' => $transaction->customer_phone,
            'customer_phone_masked' => $this->maskPhone($transaction->customer_phone),
            'product_amount' => $transaction->product_amount,
            'convenience_fee' => $transaction->convenience_fee,
            'gateway_fee' => $transaction->gateway_fee,
            'payable_amount' => $transaction->payable_amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'payment_status' => $this->paymentStatusLabel($transaction),
            'fulfillment_status' => $this->fulfillmentStatusLabel($transaction),
            'failure_reason' => $transaction->failure_reason,
            'fulfillment_reference' => $transaction->fulfillment_reference,
            'timestamp' => ($transaction->fulfilled_at ?? $transaction->updated_at)?->toIso8601String(),
            'verification_token' => $transaction->receipt_verification_token,
            'verification_url' => $this->verificationUrl($transaction),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPublicVerificationPayload(Transaction $transaction): array
    {
        return [
            'authentic' => true,
            'reference' => $transaction->reference,
            'product_type' => $transaction->product_type,
            'product_label' => $this->productLabel($transaction->product_type),
            'customer_phone_masked' => $this->maskPhone($transaction->customer_phone),
            'payable_amount' => $transaction->payable_amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'payment_status' => $this->paymentStatusLabel($transaction),
            'fulfillment_status' => $this->fulfillmentStatusLabel($transaction),
            'fulfillment_reference' => $transaction->fulfillment_reference,
            'timestamp' => ($transaction->fulfilled_at ?? $transaction->updated_at)?->toIso8601String(),
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

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? $phone;

        if (strlen($digits) < 7) {
            return '***';
        }

        return substr($digits, 0, 4).'****'.substr($digits, -3);
    }
}
