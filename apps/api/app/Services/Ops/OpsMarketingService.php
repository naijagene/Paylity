<?php

namespace App\Services\Ops;

use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherCampaign;
use App\Models\LaunchVoucherRedemption;
use App\Models\MarketingEvent;
use App\Models\TransactionReview;
use App\Services\Marketing\LaunchVoucherCampaignCapacityService;
use App\Services\Marketing\MarketingEventService;
use App\Services\Marketing\TransactionReviewService;
use App\Support\Marketing\LaunchVoucherCodeGenerator;
use Illuminate\Support\Facades\DB;

class OpsMarketingService
{
    public function __construct(
        private readonly TransactionReviewService $transactionReviewService,
        private readonly LaunchVoucherCodeGenerator $codeGenerator,
        private readonly MarketingEventService $marketingEventService,
        private readonly LaunchVoucherCampaignCapacityService $campaignCapacityService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?string $search = null): array
    {
        $campaigns = LaunchVoucherCampaign::query()->with('vouchers')->orderByDesc('id')->get();
        $vouchers = LaunchVoucher::query()->with('campaign')->orderByDesc('id')->get();

        if ($search) {
            $needle = strtoupper(trim($search));
            $vouchers = $vouchers->filter(function (LaunchVoucher $voucher) use ($needle) {
                return str_contains(strtoupper($voucher->code), $needle)
                    || str_contains((string) $voucher->code_normalized, $needle)
                    || str_contains(strtoupper((string) $voucher->name), $needle);
            });
        }

        $reviewStats = $this->transactionReviewService->aggregateStats();
        $fulfilledCount = LaunchVoucherRedemption::query()
            ->where('status', LaunchVoucherRedemption::STATUS_REDEEMED)
            ->count();
        $reservedCount = LaunchVoucherRedemption::query()
            ->where('status', LaunchVoucherRedemption::STATUS_RESERVED)
            ->count();
        $reviewCount = $reviewStats['count'];
        $shareCount = MarketingEvent::query()
            ->where('event_type', MarketingEventService::TYPE_SHARE_INITIATED)
            ->count();
        $blockedCount = MarketingEvent::query()
            ->where('event_type', MarketingEventService::TYPE_VOUCHER_BLOCKED)
            ->count();

        return [
            'refreshed_at' => now()->toIso8601String(),
            'kpis' => [
                'generated' => $vouchers->count(),
                'unused' => $vouchers->filter(fn (LaunchVoucher $voucher) => $voucher->active && ! $voucher->hasReservationOrRedemption())->count(),
                'reserved' => $reservedCount,
                'redeemed' => (int) $vouchers->sum('redeemed_count'),
                'remaining' => (int) $vouchers->sum(fn (LaunchVoucher $voucher) => $voucher->remainingRedemptions()),
                'expired' => $vouchers->filter(fn (LaunchVoucher $voucher) => $voucher->isExpired())->count(),
                'active' => $vouchers->filter(fn (LaunchVoucher $voucher) => $voucher->active && ! $voucher->isExpired())->count(),
                'blocked_attempts' => $blockedCount,
                'review_rate_pct' => $fulfilledCount > 0 ? round(($reviewCount / $fulfilledCount) * 100, 2) : 0,
                'share_rate_pct' => $reviewCount > 0 ? round(($shareCount / $reviewCount) * 100, 2) : 0,
            ],
            'reviews' => $reviewStats,
            'campaigns' => $campaigns->map(fn (LaunchVoucherCampaign $campaign) => $this->presentCampaign($campaign))->values()->all(),
            'vouchers' => $vouchers->map(fn (LaunchVoucher $voucher) => $this->presentVoucher($voucher))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createCampaign(array $input, ?string $operator = null): array
    {
        $distributionMode = (string) ($input['distribution_mode'] ?? LaunchVoucherCampaign::DISTRIBUTION_UNIQUE_CODES);
        $quantity = $distributionMode === LaunchVoucherCampaign::DISTRIBUTION_SHARED_CODE
            ? 1
            : (int) ($input['quantity'] ?? 1);
        $maxRedemptions = $distributionMode === LaunchVoucherCampaign::DISTRIBUTION_SHARED_CODE
            ? (int) ($input['max_redemptions'] ?? 0)
            : null;

        if ($distributionMode === LaunchVoucherCampaign::DISTRIBUTION_SHARED_CODE && $maxRedemptions < 1) {
            throw new \InvalidArgumentException('Shared campaigns require maximum successful redemptions.');
        }

        return DB::transaction(function () use ($input, $operator, $quantity, $distributionMode, $maxRedemptions): array {
            $campaign = LaunchVoucherCampaign::query()->create([
                'name' => (string) $input['name'],
                'amount' => (int) $input['amount'],
                'network' => $input['network'] ?? null,
                'distribution_mode' => $distributionMode,
                'generated_count' => $quantity,
                'max_redemptions' => $maxRedemptions,
                'expires_at' => $input['expires_at'] ?? null,
                'active' => (bool) ($input['active'] ?? true),
                'one_per_phone' => (bool) ($input['one_per_phone'] ?? true),
                'one_per_email' => (bool) ($input['one_per_email'] ?? true),
                'one_per_device' => (bool) ($input['one_per_device'] ?? true),
                'reservation_timeout_minutes' => (int) ($input['reservation_timeout_minutes'] ?? 30),
                'shared_code' => $distributionMode === LaunchVoucherCampaign::DISTRIBUTION_SHARED_CODE,
                'created_by' => $operator,
            ]);

            $codes = [];

            for ($index = 0; $index < $quantity; $index++) {
                $code = $this->codeGenerator->generateUnique();
                $voucher = LaunchVoucher::query()->create([
                    'campaign_id' => $campaign->id,
                    'name' => $campaign->name,
                    'code' => $code,
                    'code_normalized' => $this->codeGenerator->normalize($code),
                    'product_type' => 'airtime',
                    'amount' => $campaign->amount,
                    'network' => $campaign->network,
                    'max_redemptions' => $distributionMode === LaunchVoucherCampaign::DISTRIBUTION_SHARED_CODE
                        ? $maxRedemptions
                        : 1,
                    'expires_at' => $campaign->expires_at,
                    'active' => $campaign->active,
                    'one_per_phone' => $campaign->one_per_phone,
                    'one_per_email' => $campaign->one_per_email,
                    'one_per_device' => $campaign->one_per_device,
                    'created_by' => $operator,
                ]);

                $codes[] = $voucher->code;

                $this->marketingEventService->track(
                    MarketingEventService::TYPE_VOUCHER_GENERATED,
                    launchVoucherId: $voucher->id,
                    metadata: ['campaign_id' => $campaign->id],
                    actor: $operator ?? 'operator',
                );
            }

            return [
                'campaign' => $this->presentCampaign($campaign->fresh('vouchers')),
                'codes' => $codes,
                'shared_message' => $distributionMode === LaunchVoucherCampaign::DISTRIBUTION_SHARED_CODE
                    ? $this->buildSharedCampaignMessage($campaign, $codes[0])
                    : null,
            ];
        });
    }

    public function buildSharedCampaignMessage(LaunchVoucherCampaign $campaign, string $code): string
    {
        return sprintf(
            'Get ₦%s free airtime on PAYLITY with voucher code %s. You only pay the service charges. Try PAYLITY for fast airtime, data, and electricity payments.',
            number_format($campaign->amount),
            $code,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function setActive(LaunchVoucher $voucher, bool $active): array
    {
        $voucher->update(['active' => $active]);

        return $this->presentVoucher($voucher->fresh('campaign'));
    }

    /**
     * @return array<string, mixed>
     */
    public function setCampaignActive(LaunchVoucherCampaign $campaign, bool $active): array
    {
        $campaign->update(['active' => $active]);
        LaunchVoucher::query()
            ->where('campaign_id', $campaign->id)
            ->update(['active' => $active]);

        return $this->presentCampaign($campaign->fresh('vouchers'));
    }

    /**
     * @return array<string, mixed>
     */
    public function regenerateCode(LaunchVoucher $voucher, ?string $operator = null): array
    {
        if ($voucher->hasReservationOrRedemption()) {
            throw new \RuntimeException('Voucher codes cannot be changed after reservation or redemption.');
        }

        $code = $this->codeGenerator->generateUnique();

        $voucher->update([
            'code' => $code,
            'code_normalized' => $this->codeGenerator->normalize($code),
            'created_by' => $operator ?? $voucher->created_by,
        ]);

        return $this->presentVoucher($voucher->fresh('campaign'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportUsage(?int $campaignId = null): array
    {
        return LaunchVoucherRedemption::query()
            ->with(['voucher:id,code,name,campaign_id', 'transaction:id,reference,status,customer_phone'])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->orderByDesc('id')
            ->get()
            ->map(fn (LaunchVoucherRedemption $redemption) => [
                'voucher_code' => $redemption->voucher?->code,
                'voucher_name' => $redemption->voucher?->name,
                'campaign_id' => $redemption->campaign_id,
                'reference' => $redemption->transaction?->reference,
                'status' => $redemption->status,
                'discount_amount' => $redemption->discount_amount,
                'customer_phone' => $redemption->customer_phone,
                'customer_phone_normalized' => $redemption->customer_phone_normalized,
                'reserved_at' => $redemption->reserved_at?->toIso8601String(),
                'redeemed_at' => $redemption->redeemed_at?->toIso8601String(),
                'released_at' => $redemption->released_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCampaign(LaunchVoucherCampaign $campaign): array
    {
        $vouchers = $campaign->relationLoaded('vouchers') ? $campaign->vouchers : $campaign->vouchers()->get();
        $capacity = $this->campaignCapacityService->snapshot($campaign);
        $sharedCode = $vouchers->first()?->code;

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'amount' => $campaign->amount,
            'network' => $campaign->network,
            'distribution_mode' => $campaign->distribution_mode,
            'generated_count' => $campaign->generated_count,
            'max_redemptions' => $campaign->max_redemptions,
            'redeemed_count' => $campaign->redeemed_count,
            'expires_at' => $campaign->expires_at?->toIso8601String(),
            'active' => $campaign->active,
            'one_per_phone' => $campaign->one_per_phone,
            'one_per_email' => $campaign->one_per_email,
            'one_per_device' => $campaign->one_per_device,
            'reservation_timeout_minutes' => $campaign->reservation_timeout_minutes,
            'shared_code' => $campaign->isSharedCode(),
            'shared_code_value' => $campaign->isSharedCode() ? $sharedCode : null,
            'shared_message' => $campaign->isSharedCode() && $sharedCode
                ? $this->buildSharedCampaignMessage($campaign, $sharedCode)
                : null,
            'created_by' => $campaign->created_by,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'unused_count' => $capacity['unused_codes'],
            'reserved_count' => $capacity['reserved'],
            'released_count' => $capacity['released'],
            'expired_reservations' => $capacity['expired'],
            'remaining_capacity' => $capacity['remaining_capacity'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentVoucher(LaunchVoucher $voucher): array
    {
        $status = 'unused';
        if ($voucher->isExpired()) {
            $status = 'expired';
        } elseif (! $voucher->active) {
            $status = 'cancelled';
        } elseif ($voucher->redemptions()->where('status', LaunchVoucherRedemption::STATUS_REDEEMED)->exists()) {
            $status = 'redeemed';
        } elseif ($voucher->redemptions()->where('status', LaunchVoucherRedemption::STATUS_RESERVED)->exists()) {
            $status = 'reserved';
        }

        return [
            'id' => $voucher->id,
            'campaign_id' => $voucher->campaign_id,
            'campaign_name' => $voucher->campaign?->name,
            'distribution_mode' => $voucher->campaign?->distribution_mode,
            'name' => $voucher->name,
            'code' => $voucher->code,
            'code_suffix' => substr((string) $voucher->code_normalized, -4),
            'product_type' => $voucher->product_type,
            'amount' => $voucher->amount,
            'network' => $voucher->network,
            'max_redemptions' => $voucher->max_redemptions,
            'redeemed_count' => $voucher->redeemed_count,
            'remaining_redemptions' => $voucher->remainingRedemptions(),
            'expires_at' => $voucher->expires_at?->toIso8601String(),
            'active' => $voucher->active,
            'status' => $status,
            'immutable' => $voucher->hasReservationOrRedemption(),
            'one_per_phone' => $voucher->one_per_phone,
            'one_per_email' => $voucher->one_per_email,
            'one_per_device' => $voucher->one_per_device,
            'created_by' => $voucher->created_by,
            'created_at' => $voucher->created_at?->toIso8601String(),
        ];
    }
}
