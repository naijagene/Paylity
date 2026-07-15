<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherCampaign;
use App\Models\LaunchVoucherRedemption;
use App\Services\Ops\OpsMarketingService;
use App\Services\Ops\OpsVoucherDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpsMarketingController extends Controller
{
    public function __construct(
        private readonly OpsMarketingService $opsMarketingService,
        private readonly OpsVoucherDashboardService $opsVoucherDashboardService,
    ) {
    }

    public function snapshot(Request $request): JsonResponse
    {
        $snapshot = $this->opsMarketingService->snapshot($request->query('search'));
        $dashboard = $this->opsVoucherDashboardService->dashboardMetrics();

        $snapshot['kpis'] = array_merge($snapshot['kpis'], $dashboard['kpis']);
        $snapshot['refreshed_at'] = $dashboard['refreshed_at'];

        return ApiResponse::success(
            data: $snapshot,
            message: 'Marketing snapshot loaded.',
        );
    }

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsVoucherDashboardService->dashboardMetrics(),
            message: 'Voucher dashboard metrics loaded.',
        );
    }

    public function showCampaign(LaunchVoucherCampaign $campaign): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsVoucherDashboardService->campaignDetail($campaign),
            message: 'Campaign detail loaded.',
        );
    }

    public function redemptionLog(Request $request): JsonResponse
    {
        $paginator = $this->opsVoucherDashboardService->redemptionLog([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'campaign_id' => $request->query('campaign_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'sort_by' => $request->query('sort_by'),
            'sort_dir' => $request->query('sort_dir'),
            'per_page' => $request->query('per_page', 25),
        ]);

        $items = collect($paginator->items())
            ->map(fn (LaunchVoucherRedemption $redemption) => $this->opsVoucherDashboardService->presentRedemption($redemption))
            ->values()
            ->all();

        return ApiResponse::success(
            data: $items,
            message: 'Voucher redemption log loaded.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function abuseMonitoring(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsVoucherDashboardService->abuseMonitoring([
                'days' => $request->query('days', 14),
                'limit' => $request->query('limit', 50),
            ]),
            message: 'Voucher abuse monitoring loaded.',
        );
    }

    public function analytics(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsVoucherDashboardService->analytics(),
            message: 'Voucher analytics loaded.',
        );
    }

    public function customerLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        return ApiResponse::success(
            data: $this->opsVoucherDashboardService->customerLookup($validated['q']),
            message: 'Customer voucher lookup completed.',
        );
    }

    public function extendExpiry(Request $request, LaunchVoucherCampaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'expires_at' => ['required', 'date', 'after:now'],
        ]);

        return ApiResponse::success(
            data: $this->opsVoucherDashboardService->extendExpiry($campaign, \Illuminate\Support\Carbon::parse($validated['expires_at'])),
            message: 'Campaign expiry extended.',
        );
    }

    public function increaseCapacity(Request $request, LaunchVoucherCampaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'max_redemptions' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        try {
            $detail = $this->opsVoucherDashboardService->increaseCapacity($campaign, (int) $validated['max_redemptions']);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => 'CAMPAIGN_CAPACITY_INVALID'],
                status: 422,
            );
        }

        return ApiResponse::success(
            data: $detail,
            message: 'Campaign capacity updated.',
        );
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'in:500,1000'],
            'distribution_mode' => ['required', 'string', 'in:unique_codes,shared_code'],
            'quantity' => [
                'required_if:distribution_mode,unique_codes',
                'prohibited_if:distribution_mode,shared_code',
                'nullable',
                'integer',
                'in:1,5,10,25,50,100',
            ],
            'max_redemptions' => [
                'required_if:distribution_mode,shared_code',
                'prohibited_if:distribution_mode,unique_codes',
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
            'network' => ['nullable', 'string', 'max:32'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'one_per_phone' => ['sometimes', 'boolean'],
            'one_per_email' => ['sometimes', 'boolean'],
            'one_per_device' => ['sometimes', 'boolean'],
            'reservation_timeout_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'shared_code' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success(
            data: $this->opsMarketingService->createCampaign($validated, $request->header('X-Operator-Name')),
            message: 'Launch voucher campaign created.',
            status: 201,
        );
    }

    public function setActive(Request $request, LaunchVoucher $voucher): JsonResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        return ApiResponse::success(
            data: $this->opsMarketingService->setActive($voucher, (bool) $validated['active']),
            message: 'Launch voucher status updated.',
        );
    }

    public function setCampaignActive(Request $request, LaunchVoucherCampaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        return ApiResponse::success(
            data: $this->opsMarketingService->setCampaignActive($campaign, (bool) $validated['active']),
            message: 'Launch voucher campaign status updated.',
        );
    }

    public function regenerateCode(Request $request, LaunchVoucher $voucher): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsMarketingService->regenerateCode($voucher, $request->header('X-Operator-Name')),
            message: 'Replacement voucher code generated.',
        );
    }

    public function exportUsage(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsMarketingService->exportUsage($request->integer('campaign_id') ?: null),
            message: 'Voucher usage exported.',
        );
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $rows = $this->opsMarketingService->exportUsage($request->integer('campaign_id') ?: null);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['voucher_code', 'campaign_id', 'reference', 'status', 'discount_amount', 'customer_phone', 'reserved_at', 'redeemed_at']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['voucher_code'] ?? '',
                    $row['campaign_id'] ?? '',
                    $row['reference'] ?? '',
                    $row['status'] ?? '',
                    $row['discount_amount'] ?? '',
                    $row['customer_phone'] ?? '',
                    $row['reserved_at'] ?? '',
                    $row['redeemed_at'] ?? '',
                ]);
            }

            fclose($handle);
        }, 'voucher-usage-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
