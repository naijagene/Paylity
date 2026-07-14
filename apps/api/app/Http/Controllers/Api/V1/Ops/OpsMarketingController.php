<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Models\LaunchVoucher;
use App\Services\Ops\OpsMarketingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsMarketingController extends Controller
{
    public function __construct(
        private readonly OpsMarketingService $opsMarketingService,
    ) {
    }

    public function snapshot(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsMarketingService->snapshot(),
            message: 'Marketing snapshot loaded.',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'unique:launch_vouchers,code'],
            'amount' => ['required', 'integer', 'min:1'],
            'network' => ['nullable', 'string', 'max:32'],
            'max_redemptions' => ['required', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'one_per_phone' => ['sometimes', 'boolean'],
            'one_per_email' => ['sometimes', 'boolean'],
            'one_per_device' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success(
            data: $this->opsMarketingService->create($validated, $request->header('X-Operator-Name')),
            message: 'Launch voucher created.',
            status: 201,
        );
    }

    public function update(Request $request, LaunchVoucher $voucher): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:64', 'unique:launch_vouchers,code,'.$voucher->id],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'network' => ['nullable', 'string', 'max:32'],
            'max_redemptions' => ['sometimes', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'one_per_phone' => ['sometimes', 'boolean'],
            'one_per_email' => ['sometimes', 'boolean'],
            'one_per_device' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success(
            data: $this->opsMarketingService->update($voucher, $validated),
            message: 'Launch voucher updated.',
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

    public function exportUsage(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsMarketingService->exportUsage(),
            message: 'Voucher usage exported.',
        );
    }
}
