<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Exceptions\FulfillmentException;
use App\Exceptions\VTPassConfigurationException;
use App\Exceptions\VTPassException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Ops\OpsTransactionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsTransactionController extends Controller
{
    public function __construct(
        private readonly OpsTransactionService $opsTransactionService,
        private readonly FulfillmentService $fulfillmentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->opsTransactionService->search([
            'reference' => $request->query('reference'),
            'phone' => $request->query('phone'),
            'status' => $request->query('status'),
            'product_type' => $request->query('product_type'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'per_page' => $request->query('per_page', 20),
        ]);

        $items = collect($paginator->items())
            ->map(fn (Transaction $transaction) => $this->opsTransactionService->toListItem($transaction))
            ->values()
            ->all();

        return ApiResponse::success(
            data: $items,
            message: 'Operations transactions retrieved successfully.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
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
            data: $this->opsTransactionService->toDetailResponse($transaction),
            message: 'Operations transaction retrieved successfully.',
        );
    }

    public function fulfill(string $reference): JsonResponse
    {
        if (! $this->fulfillmentService->isEnabled()) {
            return ApiResponse::error(
                message: 'VTPass fulfillment is disabled.',
                errors: ['code' => 'VTPASS_DISABLED'],
                status: 503,
            );
        }

        try {
            $transaction = $this->fulfillmentService->fulfillByReference($reference);

            return ApiResponse::success(
                data: $this->fulfillmentService->toResponse($transaction),
                message: 'Fulfillment completed successfully.',
            );
        } catch (FulfillmentException $exception) {
            $status = $exception->errorCode === 'TRANSACTION_NOT_FOUND' ? 404 : 422;

            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: $status,
            );
        } catch (VTPassConfigurationException|VTPassException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: 502,
            );
        }
    }
}
