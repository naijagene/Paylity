<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ProductCatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function __construct(
        private readonly ProductCatalogService $productCatalogService,
    ) {
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', Rule::in(['airtime', 'data', 'electricity'])],
            'include_hidden' => ['nullable', 'boolean'],
        ]);

        $includeHidden = (bool) ($validated['include_hidden'] ?? false)
            && $this->operatorKeyValid($request);

        return ApiResponse::success(
            data: $this->productCatalogService->catalogResponse(
                category: $validated['category'] ?? null,
                includeHidden: $includeHidden,
            ),
            message: 'Product catalog retrieved successfully.',
        );
    }

    private function operatorKeyValid(Request $request): bool
    {
        $configuredKey = (string) config('services.operator.access_key');

        if ($configuredKey === '') {
            return false;
        }

        $providedKey = (string) $request->header('X-Operator-Key', '');

        return $providedKey !== '' && hash_equals($configuredKey, $providedKey);
    }
}
