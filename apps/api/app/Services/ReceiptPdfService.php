<?php

namespace App\Services;

use App\Models\Transaction;

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
        $receipt = $this->receiptService->buildReceiptPayload($transaction);

        if ($receipt === null) {
            throw new \RuntimeException('Receipt is not available for this transaction.');
        }

        $verificationUrl = $receipt['verification_url'];
        $qrCodeDataUri = $this->buildQrCodeDataUri($verificationUrl);

        $html = view('receipts.pdf', [
            'receipt' => $receipt,
            'qrCodeDataUri' => $qrCodeDataUri,
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
}
