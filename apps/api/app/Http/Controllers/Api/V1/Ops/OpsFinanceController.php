<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Finance\FinancialCloseService;
use App\Services\Finance\LedgerBackfillService;
use App\Services\Finance\SettlementReconciliationService;
use App\Services\Ops\OpsFinanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsFinanceController extends Controller
{
    public function __construct(
        private readonly OpsFinanceService $opsFinanceService,
        private readonly SettlementReconciliationService $settlementReconciliationService,
        private readonly LedgerBackfillService $ledgerBackfillService,
        private readonly FinancialCloseService $financialCloseService,
    ) {
    }

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsFinanceService->dashboard(),
            message: 'Finance dashboard loaded.',
        );
    }

    public function ledgerEntries(Request $request): JsonResponse
    {
        $paginator = $this->opsFinanceService->ledgerEntries($request->all());

        return ApiResponse::success(
            data: collect($paginator->items())->map(fn ($item) => $item)->values()->all(),
            message: 'Ledger entries loaded.',
            meta: [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function transactionFinance(string $reference): JsonResponse
    {
        $transaction = Transaction::query()->where('reference', $reference)->first();

        if (! $transaction) {
            return ApiResponse::error('Transaction not found.', ['code' => 'TRANSACTION_NOT_FOUND'], 404);
        }

        return ApiResponse::success(
            data: $this->opsFinanceService->transactionFinance($transaction),
            message: 'Transaction finance detail loaded.',
        );
    }

    public function exportDailySummary(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsFinanceService->exportDailySummary(
                $request->query('date_from'),
                $request->query('date_to'),
            ),
            message: 'Daily financial summary export ready.',
        );
    }

    public function reconcileSettlements(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', true);

        $summary = $this->settlementReconciliationService->reconcile(
            date: $request->query('date'),
            reference: $request->query('reference'),
            limit: (int) $request->query('limit', 50),
            dryRun: $dryRun,
            repair: ! $dryRun,
        );

        return ApiResponse::success(
            data: $summary,
            message: $dryRun ? 'Settlement reconciliation dry run complete.' : 'Settlement reconciliation complete.',
        );
    }

    public function backfill(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', true);

        $summary = $this->ledgerBackfillService->backfill(
            reference: $request->query('reference'),
            since: $request->query('since'),
            date: $request->query('date'),
            limit: (int) $request->query('limit', 50),
            dryRun: $dryRun,
            repair: ! $dryRun,
        );

        return ApiResponse::success(
            data: $summary,
            message: $dryRun ? 'Ledger backfill dry run complete.' : 'Ledger backfill complete.',
        );
    }

    public function close(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', true);

        $result = $this->financialCloseService->close(
            date: $request->query('date'),
            dryRun: $dryRun,
            repair: ! $dryRun,
            force: $request->boolean('force', false),
        );

        return ApiResponse::success(
            data: $result,
            message: $dryRun ? 'Financial close dry run complete.' : 'Financial close complete.',
        );
    }
}
