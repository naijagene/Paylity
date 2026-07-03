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
        ]);

        return ApiResponse::success(
            data: $this->productCatalogService->catalogResponse($validated['category'] ?? null),
            message: 'Product catalog retrieved successfully.',
        );
    }
}
