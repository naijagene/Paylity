<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Services\Ops\OpsReconciliationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsReconciliationController extends Controller
{
    public function __construct(
        private readonly OpsReconciliationService $opsReconciliationService,
    ) {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsReconciliationService->snapshot(),
            message: 'Reconciliation snapshot retrieved successfully.',
        );
    }

    public function reconcilePayment(string $reference): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsReconciliationService->reconcilePayment($reference),
            message: 'Payment reconciliation completed.',
        );
    }

    public function reconcileFulfillment(string $reference): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsReconciliationService->reconcileFulfillment($reference),
            message: 'Fulfillment reconciliation completed.',
        );
    }

    public function retry(string $reference): JsonResponse
    {
        $result = $this->opsReconciliationService->retryConfirmedFailure($reference);

        if ($result['outcome'] !== 'fulfilled') {
            return ApiResponse::error(
                message: $result['reason'] ?? 'Retry could not complete.',
                errors: ['code' => strtoupper((string) $result['outcome'])],
                status: 422,
            );
        }

        return ApiResponse::success(
            data: $result,
            message: 'Fulfillment retry completed successfully.',
        );
    }

    public function manualReview(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return ApiResponse::success(
            data: $this->opsReconciliationService->moveToManualReview(
                $reference,
                $validated['reason'],
            ),
            message: 'Transaction moved to manual review.',
        );
    }

    public function resumeAutomation(string $reference): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsReconciliationService->resumeAutomation($reference),
            message: 'Automation resumed for transaction.',
        );
    }
}
