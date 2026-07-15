<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherCampaign;
use App\Services\Ops\OpsMarketingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpsMarketingController extends Controller
{
    public function __construct(
        private readonly OpsMarketingService $opsMarketingService,
    ) {
    }

    public function snapshot(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsMarketingService->snapshot($request->query('search')),
            message: 'Marketing snapshot loaded.',
        );
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'in:500,1000'],
            'distribution_mode' => ['required', 'string', 'in:unique_codes,shared_code'],
            'quantity' => ['nullable', 'integer', 'in:1,5,10,25,50,100'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:10000'],
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
