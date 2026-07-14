<?php

namespace App\Services\Launch;

use App\Services\FeeService;
use App\Services\Finance\PaystackGatewayFeeCalculator;

class PricingAuditService
{
    public const LAUNCH_AMOUNTS = [100, 200, 500, 1000, 2000, 5000, 10000, 20000];

    public function __construct(
        private readonly PaystackGatewayFeeCalculator $calculator,
        private readonly FeeService $feeService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function audit(string $product = 'airtime', ?int $amount = null): array
    {
        $amounts = $amount !== null ? [$amount] : self::LAUNCH_AMOUNTS;
        $rows = [];

        foreach ($amounts as $productAmount) {
            $convenienceFee = $this->feeService->convenienceFeeFor($product);
            $row = $this->calculator->auditLaunchAmount($productAmount, $convenienceFee, $productAmount);
            $rows[] = array_merge($row, [
                'product' => $product,
                'status' => $row['negative_margin'] ? 'negative' : 'positive',
            ]);
        }

        return [
            'product' => $product,
            'amounts' => $rows,
            'negative_margin_count' => collect($rows)->where('negative_margin', true)->count(),
            'all_positive' => collect($rows)->every(fn (array $row) => ! $row['negative_margin']),
        ];
    }
}
