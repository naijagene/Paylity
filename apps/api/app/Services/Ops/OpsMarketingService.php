<?php

namespace App\Services\Ops;

use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherRedemption;
use App\Models\MarketingEvent;
use App\Models\TransactionReview;
use App\Services\Marketing\MarketingEventService;
use App\Services\Marketing\TransactionReviewService;
use Illuminate\Support\Str;

class OpsMarketingService
{
    public function __construct(
        private readonly TransactionReviewService $transactionReviewService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $vouchers = LaunchVoucher::query()->get();
        $reviewStats = $this->transactionReviewService->aggregateStats();
        $fulfilledCount = LaunchVoucherRedemption::query()
            ->where('status', LaunchVoucherRedemption::STATUS_COMPLETED)
            ->count();
        $reviewCount = $reviewStats['count'];
        $shareCount = MarketingEvent::query()
            ->where('event_type', MarketingEventService::TYPE_SHARE_INITIATED)
            ->count();

        return [
            'refreshed_at' => now()->toIso8601String(),
            'kpis' => [
                'generated' => $vouchers->count(),
                'redeemed' => (int) $vouchers->sum('redeemed_count'),
                'remaining' => (int) $vouchers->sum(fn (LaunchVoucher $voucher) => $voucher->remainingRedemptions()),
                'expired' => $vouchers->filter(fn (LaunchVoucher $voucher) => $voucher->isExpired())->count(),
                'active' => $vouchers->filter(fn (LaunchVoucher $voucher) => $voucher->active && ! $voucher->isExpired())->count(),
                'review_rate_pct' => $fulfilledCount > 0 ? round(($reviewCount / $fulfilledCount) * 100, 2) : 0,
                'share_rate_pct' => $reviewCount > 0 ? round(($shareCount / $reviewCount) * 100, 2) : 0,
            ],
            'reviews' => $reviewStats,
            'vouchers' => $vouchers->map(fn (LaunchVoucher $voucher) => $this->presentVoucher($voucher))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(array $input, ?string $operator = null): array
    {
        $voucher = LaunchVoucher::query()->create([
            'name' => (string) $input['name'],
            'code' => Str::upper((string) $input['code']),
            'product_type' => 'airtime',
            'amount' => (int) $input['amount'],
            'network' => $input['network'] ?? null,
            'max_redemptions' => (int) $input['max_redemptions'],
            'expires_at' => $input['expires_at'] ?? null,
            'active' => (bool) ($input['active'] ?? true),
            'one_per_phone' => (bool) ($input['one_per_phone'] ?? true),
            'one_per_email' => (bool) ($input['one_per_email'] ?? false),
            'one_per_device' => (bool) ($input['one_per_device'] ?? true),
            'created_by' => $operator,
        ]);

        return $this->presentVoucher($voucher);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(LaunchVoucher $voucher, array $input): array
    {
        $voucher->update([
            'name' => $input['name'] ?? $voucher->name,
            'code' => isset($input['code']) ? Str::upper((string) $input['code']) : $voucher->code,
            'amount' => $input['amount'] ?? $voucher->amount,
            'network' => array_key_exists('network', $input) ? $input['network'] : $voucher->network,
            'max_redemptions' => $input['max_redemptions'] ?? $voucher->max_redemptions,
            'expires_at' => array_key_exists('expires_at', $input) ? $input['expires_at'] : $voucher->expires_at,
            'active' => array_key_exists('active', $input) ? (bool) $input['active'] : $voucher->active,
            'one_per_phone' => array_key_exists('one_per_phone', $input) ? (bool) $input['one_per_phone'] : $voucher->one_per_phone,
            'one_per_email' => array_key_exists('one_per_email', $input) ? (bool) $input['one_per_email'] : $voucher->one_per_email,
            'one_per_device' => array_key_exists('one_per_device', $input) ? (bool) $input['one_per_device'] : $voucher->one_per_device,
        ]);

        return $this->presentVoucher($voucher->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function setActive(LaunchVoucher $voucher, bool $active): array
    {
        $voucher->update(['active' => $active]);

        return $this->presentVoucher($voucher->fresh());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportUsage(): array
    {
        return LaunchVoucherRedemption::query()
            ->with(['voucher:id,code,name', 'transaction:id,reference,status,customer_phone'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (LaunchVoucherRedemption $redemption) => [
                'voucher_code' => $redemption->voucher?->code,
                'voucher_name' => $redemption->voucher?->name,
                'reference' => $redemption->transaction?->reference,
                'status' => $redemption->status,
                'discount_amount' => $redemption->discount_amount,
                'customer_phone' => $redemption->customer_phone,
                'redeemed_at' => $redemption->redeemed_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentVoucher(LaunchVoucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'name' => $voucher->name,
            'code' => $voucher->code,
            'product_type' => $voucher->product_type,
            'amount' => $voucher->amount,
            'network' => $voucher->network,
            'max_redemptions' => $voucher->max_redemptions,
            'redeemed_count' => $voucher->redeemed_count,
            'remaining_redemptions' => $voucher->remainingRedemptions(),
            'expires_at' => $voucher->expires_at?->toIso8601String(),
            'active' => $voucher->active,
            'one_per_phone' => $voucher->one_per_phone,
            'one_per_email' => $voucher->one_per_email,
            'one_per_device' => $voucher->one_per_device,
            'created_by' => $voucher->created_by,
            'created_at' => $voucher->created_at?->toIso8601String(),
        ];
    }
}
