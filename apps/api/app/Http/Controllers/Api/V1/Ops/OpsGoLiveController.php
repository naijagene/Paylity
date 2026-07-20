<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Models\PaymentCertificationRun;
use App\Services\Launch\DatabaseBackupService;
use App\Services\Launch\LaunchAuditService;
use App\Services\Launch\LaunchModeService;
use App\Services\Launch\PaymentCertificationService;
use App\Services\Launch\PaymentLivePreflightService;
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
        private readonly PaymentLivePreflightService $paymentLivePreflightService,
        private readonly PaymentCertificationService $paymentCertificationService,
        private readonly LaunchAuditService $launchAuditService,
        private readonly LaunchModeService $launchModeService,
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
            'confirm_maintenance' => ['sometimes', 'boolean'],
            'reason' => ['sometimes', 'string', 'max:500'],
        ]);

        if ($validated['mode'] === 'live' && ! $request->boolean('confirm_production')) {
            return ApiResponse::error(
                message: 'Production mode requires explicit confirmation.',
                errors: ['confirm_production' => ['Set confirm_production=true to switch to live production.']],
                status: 422,
            );
        }

        if ($validated['mode'] === 'maintenance' && ! $request->boolean('confirm_maintenance')) {
            return ApiResponse::error(
                message: 'Maintenance mode requires explicit confirmation.',
                errors: ['confirm_maintenance' => ['Set confirm_maintenance=true to enter maintenance mode.']],
                status: 422,
            );
        }

        $previous = $this->launchModeService->snapshot();
        $next = $this->opsGoLiveService->setLaunchMode($validated['mode']);
        $operator = $request->header('X-Operator-Name') ?: 'ops';

        $this->launchAuditService->record(
            action: LaunchAuditService::ACTION_LAUNCH_MODE_CHANGED,
            previous: ['launch_mode' => $previous['mode'] ?? null],
            new: ['launch_mode' => $next['mode'] ?? $validated['mode']],
            operator: $operator,
            reason: $validated['reason'] ?? null,
            request: $request,
        );

        if ($validated['mode'] === LaunchModeService::MODE_LIVE) {
            $this->launchAuditService->record(
                action: LaunchAuditService::ACTION_PRODUCTION_ENABLED,
                previous: ['launch_mode' => $previous['mode'] ?? null],
                new: ['launch_mode' => $next['mode'] ?? $validated['mode']],
                operator: $operator,
                reason: $validated['reason'] ?? null,
                request: $request,
            );
        }

        if ($validated['mode'] === LaunchModeService::MODE_MAINTENANCE) {
            $this->launchAuditService->record(
                action: LaunchAuditService::ACTION_MAINTENANCE_ENTERED,
                previous: ['launch_mode' => $previous['mode'] ?? null],
                new: ['launch_mode' => $next['mode'] ?? $validated['mode']],
                operator: $operator,
                reason: $validated['reason'] ?? null,
                request: $request,
            );
        }

        if ($validated['mode'] === LaunchModeService::MODE_SOFT_LAUNCH) {
            $this->launchAuditService->record(
                action: LaunchAuditService::ACTION_SOFT_LAUNCH_RESTORED,
                previous: ['launch_mode' => $previous['mode'] ?? null],
                new: ['launch_mode' => $next['mode'] ?? $validated['mode']],
                operator: $operator,
                reason: $validated['reason'] ?? null,
                request: $request,
            );
        }

        return ApiResponse::success(
            data: $next,
            message: 'Launch mode updated.',
        );
    }

    public function paymentCertificationSnapshot(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->paymentCertificationService->snapshot(),
            message: 'Live payment certification snapshot loaded.',
        );
    }

    public function paymentCertificationPreflight(Request $request): JsonResponse
    {
        $report = $this->paymentLivePreflightService->run(
            strict: $request->boolean('strict', false),
            reference: $request->input('reference'),
            persist: true,
        );

        $this->launchAuditService->record(
            action: LaunchAuditService::ACTION_LIVE_PREFLIGHT,
            new: [
                'verdict' => $report['verdict'] ?? $report['status'],
                'strict' => $request->boolean('strict', false),
            ],
            operator: $request->header('X-Operator-Name'),
            request: $request,
        );

        return ApiResponse::success(
            data: $report,
            message: 'Live payment preflight completed.',
        );
    }

    public function createPaymentCertification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product' => ['sometimes', 'string', 'in:airtime,data,electricity'],
            'amount' => ['sometimes', 'integer', 'min:50', 'max:50000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'network' => ['sometimes', 'nullable', 'string', 'max:20'],
            'confirm_live_certification' => ['required', 'boolean', 'accepted'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $run = $this->paymentCertificationService->createSession(
            productType: (string) ($validated['product'] ?? 'airtime'),
            productAmountNaira: (int) ($validated['amount'] ?? 100),
            phone: $validated['phone'] ?? null,
            network: $validated['network'] ?? null,
            operator: $request->header('X-Operator-Name'),
            force: (bool) ($validated['force'] ?? false),
        );

        $this->launchAuditService->record(
            action: LaunchAuditService::ACTION_CERTIFICATION_CREATED,
            new: $run,
            operator: $request->header('X-Operator-Name'),
            runId: (int) ($run['id'] ?? 0),
            request: $request,
        );

        return ApiResponse::success(
            data: $run,
            message: 'Live payment certification session created.',
        );
    }

    public function linkPaymentCertificationReference(Request $request, PaymentCertificationRun $run): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
        ]);

        $payload = $this->paymentCertificationService->linkReference(
            $run,
            $validated['reference'],
            $request->header('X-Operator-Name'),
        );

        $this->launchAuditService->record(
            action: LaunchAuditService::ACTION_CERTIFICATION_LINKED,
            new: ['reference' => $validated['reference']],
            operator: $request->header('X-Operator-Name'),
            reference: $validated['reference'],
            runId: $run->id,
            request: $request,
        );

        return ApiResponse::success(
            data: $payload,
            message: 'Certification transaction linked.',
        );
    }

    public function finalizePaymentCertification(Request $request, PaymentCertificationRun $run): JsonResponse
    {
        $validated = $request->validate([
            'confirm_finalize' => ['required', 'boolean', 'accepted'],
        ]);

        $payload = $this->paymentCertificationService->finalize(
            $run,
            $request->header('X-Operator-Name'),
        );

        $this->launchAuditService->record(
            action: LaunchAuditService::ACTION_CERTIFICATION_FINALIZED,
            new: ['result' => $payload['result'] ?? PaymentCertificationRun::RESULT_INCOMPLETE],
            operator: $request->header('X-Operator-Name'),
            runId: $run->id,
            request: $request,
        );

        return ApiResponse::success(
            data: $payload,
            message: 'Live payment certification finalized.',
        );
    }

    public function exportPaymentCertification(PaymentCertificationRun $run): JsonResponse
    {
        return ApiResponse::success(
            data: $this->paymentCertificationService->export($run),
            message: 'Live payment certification evidence exported.',
        );
    }

    public function refreshPaymentCertification(Request $request, PaymentCertificationRun $run): JsonResponse
    {
        return ApiResponse::success(
            data: $this->paymentCertificationService->refreshRun(
                $run,
                $request->header('X-Operator-Name'),
            ),
            message: 'Live payment certification refreshed.',
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
