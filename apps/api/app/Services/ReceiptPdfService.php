<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

class ReceiptPdfService
{
    public function __construct(
        private readonly ReceiptService $receiptService,
    ) {
    }

    /**
     * @return array{html: string, filename: string}
     */
    public function render(Transaction $transaction): array
    {
        $cacheKey = sprintf(
            'receipt.html.%s.%s',
            $transaction->reference,
            $transaction->updated_at?->timestamp ?? '0',
        );

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($transaction): array {
            return $this->buildReceiptHtml($transaction);
        });
    }

    /**
     * @return array{html: string, filename: string}
     */
    private function buildReceiptHtml(Transaction $transaction): array
    {
        $receipt = $this->receiptService->buildReceiptPayload($transaction);

        if ($receipt === null) {
            throw new \RuntimeException('Receipt is not available for this transaction.');
        }

        $verificationUrl = $receipt['verification_url'];
        $qrCodeDataUri = $this->buildQrCodeDataUri($verificationUrl);
        $badgeContext = $this->buildBadgeContext($receipt);

        $html = view('receipts.pdf', [
            'receipt' => $receipt,
            'qrCodeDataUri' => $qrCodeDataUri,
            ...$badgeContext,
        ])->render();

        return [
            'html' => $html,
            'filename' => 'paylity-receipt-'.$transaction->reference.'.html',
        ];
    }

    private function buildQrCodeDataUri(string $content): string
    {
        $encoded = rawurlencode($content);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data='.$encoded;

        return $qrUrl;
    }

    /**
     * @param  array<string, mixed>  $receipt
     * @return array{
     *     paymentBadgeLabel: string,
     *     paymentBadgeVariant: string,
     *     fulfillmentBadgeLabel: string,
     *     fulfillmentBadgeVariant: string
     * }
     */
    private function buildBadgeContext(array $receipt): array
    {
        $status = (string) ($receipt['status'] ?? 'payment_pending');

        return match ($status) {
            'created', 'payment_pending' => [
                'paymentBadgeLabel' => 'Payment Pending',
                'paymentBadgeVariant' => 'info',
                'fulfillmentBadgeLabel' => 'Awaiting Payment',
                'fulfillmentBadgeVariant' => 'info',
            ],
            'payment_failed' => [
                'paymentBadgeLabel' => 'Payment Failed',
                'paymentBadgeVariant' => 'failed',
                'fulfillmentBadgeLabel' => 'Not Started',
                'fulfillmentBadgeVariant' => 'info',
            ],
            'payment_success', 'fulfillment_pending' => [
                'paymentBadgeLabel' => 'Payment Successful',
                'paymentBadgeVariant' => 'success',
                'fulfillmentBadgeLabel' => 'Processing',
                'fulfillmentBadgeVariant' => 'processing',
            ],
            'fulfilled' => [
                'paymentBadgeLabel' => 'Payment Successful',
                'paymentBadgeVariant' => 'success',
                'fulfillmentBadgeLabel' => 'Delivered',
                'fulfillmentBadgeVariant' => 'success',
            ],
            'failed' => [
                'paymentBadgeLabel' => 'Payment Successful',
                'paymentBadgeVariant' => 'success',
                'fulfillmentBadgeLabel' => 'Delivery Failed',
                'fulfillmentBadgeVariant' => 'failed',
            ],
            default => [
                'paymentBadgeLabel' => 'Payment Pending',
                'paymentBadgeVariant' => 'info',
                'fulfillmentBadgeLabel' => 'Awaiting Payment',
                'fulfillmentBadgeVariant' => 'info',
            ],
        };
    }
}
