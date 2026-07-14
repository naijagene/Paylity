<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Services\Launch\DatabaseBackupService;
use App\Services\Launch\PricingAuditService;
use App\Services\Ops\OpsGoLiveService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function heartbeat(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsGoLiveService->heartbeat(),
            message: 'Scheduler heartbeat loaded.',
        );
    }

    public function checklist(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsGoLiveService->checklist(),
            message: 'Launch checklist loaded.',
        );
    }

    public function updateChecklist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['boolean'],
        ]);

        return ApiResponse::success(
            data: $this->opsGoLiveService->updateChecklist($validated['items']),
            message: 'Launch checklist updated.',
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

    public function setMode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:staging,soft_launch,live,maintenance'],
            'confirm_production' => ['sometimes', 'boolean'],
        ]);

        if ($validated['mode'] === 'live' && ! $request->boolean('confirm_production')) {
            return ApiResponse::error(
                message: 'Production mode requires explicit confirmation.',
                errors: ['confirm_production' => ['Set confirm_production=true to switch to live production.']],
                status: 422,
            );
        }

        return ApiResponse::success(
            data: $this->opsGoLiveService->setLaunchMode($validated['mode']),
            message: 'Launch mode updated.',
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

    public function exportJson(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsGoLiveService->exportJson($request->header('X-Operator-Name')),
            message: 'Launch report exported.',
        );
    }

    public function exportPdf(Request $request): Response
    {
        $rendered = $this->opsGoLiveService->exportPdf($request->header('X-Operator-Name'));

        return response($rendered['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$rendered['filename'].'"',
        ]);
    }
}
