<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ReceiptPdfService;
use App\Services\ReceiptService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $receiptService,
        private readonly ReceiptPdfService $receiptPdfService,
    ) {
    }

    public function show(string $reference): JsonResponse
    {
        $transaction = Transaction::query()
            ->where('reference', $reference)
            ->first();

        if (! $transaction) {
            return ApiResponse::error(
                message: 'Transaction not found.',
                errors: ['code' => 'TRANSACTION_NOT_FOUND'],
                status: 404,
            );
        }

        $receipt = $this->receiptService->buildReceiptPayload($transaction);

        if ($receipt === null) {
            return ApiResponse::error(
                message: 'Receipt is not available for this transaction.',
                errors: ['code' => 'RECEIPT_NOT_AVAILABLE'],
                status: 422,
            );
        }

        return ApiResponse::success(
            data: $receipt,
            message: 'Receipt retrieved successfully.',
        );
    }

    public function download(string $reference): Response|JsonResponse
    {
        $transaction = Transaction::query()
            ->where('reference', $reference)
            ->first();

        if (! $transaction) {
            return ApiResponse::error(
                message: 'Transaction not found.',
                errors: ['code' => 'TRANSACTION_NOT_FOUND'],
                status: 404,
            );
        }

        try {
            $rendered = $this->receiptPdfService->render($transaction);
        } catch (\RuntimeException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => 'RECEIPT_NOT_AVAILABLE'],
                status: 422,
            );
        }

        return response($rendered['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$rendered['filename'].'"',
        ]);
    }
}
