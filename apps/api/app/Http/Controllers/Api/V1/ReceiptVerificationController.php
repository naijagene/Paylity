<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ReceiptService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReceiptVerificationController extends Controller
{
    public function __construct(
        private readonly ReceiptService $receiptService,
    ) {
    }

    public function show(string $token): JsonResponse
    {
        $transaction = Transaction::query()
            ->where('receipt_verification_token', $token)
            ->first();

        if (! $transaction || ! $this->receiptService->isReceiptAvailable($transaction)) {
            return ApiResponse::error(
                message: 'Receipt could not be verified.',
                errors: ['code' => 'RECEIPT_NOT_FOUND'],
                status: 404,
            );
        }

        return ApiResponse::success(
            data: $this->receiptService->buildPublicVerificationPayload($transaction),
            message: 'Receipt verified successfully.',
        );
    }
}
