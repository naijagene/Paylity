<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Services\Launch\DatabaseBackupService;
use App\Services\Launch\PricingAuditService;
use App\Services\Ops\OpsGoLiveService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsGoLiveController extends Controller
{
    public function __construct(
        private readonly OpsGoLiveService $opsGoLiveService,
        private readonly DatabaseBackupService $databaseBackupService,
        private readonly PricingAuditService $pricingAuditService,
    ) {
    }

    public function snapshot(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsGoLiveService->snapshot(),
            message: 'Go-live snapshot loaded.',
        );
    }

    public function preflight(Request $request): JsonResponse
    {
        $report = $this->opsGoLiveService->runPreflight(
            strict: $request->boolean('strict', false),
            reference: $request->query('reference'),
        );

        return ApiResponse::success(
            data: $report,
            message: 'Launch preflight completed.',
        );
    }

    public function backup(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->databaseBackupService->create(),
            message: 'Database backup created.',
        );
    }

    public function verifyBackup(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->databaseBackupService->verify($request->query('file')),
            message: 'Database backup verified.',
        );
    }

    public function pricingAudit(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->pricingAuditService->audit(
                product: (string) $request->query('product', 'airtime'),
                amount: $request->query('amount') !== null ? (int) $request->query('amount') : null,
            ),
            message: 'Pricing audit completed.',
        );
    }
}
