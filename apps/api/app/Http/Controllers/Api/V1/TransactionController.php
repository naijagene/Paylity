<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
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

        return ApiResponse::success(
            data: $this->transactionService->toDetailResponse($transaction),
            message: 'Transaction retrieved successfully.',
        );
    }
}
