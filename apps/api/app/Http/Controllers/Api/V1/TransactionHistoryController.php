<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionHistoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionHistoryController extends Controller
{
    public function __construct(
        private readonly TransactionHistoryService $transactionHistoryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $phone = trim((string) $request->query('phone', ''));

        if ($phone === '') {
            return ApiResponse::error(
                message: 'Phone number is required.',
                errors: ['code' => 'PHONE_REQUIRED'],
                status: 422,
            );
        }

        $paginator = $this->transactionHistoryService->search([
            'phone' => $phone,
            'status_group' => $request->query('status_group'),
            'product_type' => $request->query('product_type'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'per_page' => $request->query('per_page', 20),
        ]);

        $items = collect($paginator->items())
            ->map(fn (Transaction $transaction) => $this->transactionHistoryService->toListItem($transaction))
            ->values()
            ->all();

        return ApiResponse::success(
            data: $items,
            message: 'Transaction history retrieved successfully.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }
}
