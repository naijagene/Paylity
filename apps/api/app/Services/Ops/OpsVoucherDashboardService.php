<?php

namespace App\Services\Ops;

use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherCampaign;
use App\Models\LaunchVoucherRedemption;
use App\Models\MarketingEvent;
use App\Models\Transaction;
use App\Services\Marketing\LaunchVoucherCampaignCapacityService;
use App\Services\Marketing\MarketingEventService;
use App\Support\Marketing\LaunchVoucherCodeGenerator;
use App\Support\Marketing\VoucherIdentityNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OpsVoucherDashboardService
{
    public function __construct(
        private readonly OpsMarketingService $opsMarketingService,
        private readonly LaunchVoucherCampaignCapacityService $campaignCapacityService,
        private readonly LaunchVoucherCodeGenerator $codeGenerator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardMetrics(): array
    {
        $campaigns = LaunchVoucherCampaign::query()->get();
        $vouchers = LaunchVoucher::query()->get();

        $remainingCapacity = $campaigns->sum(function (LaunchVoucherCampaign $campaign): int {
            return $this->campaignCapacityService->remainingCapacity($campaign);
        });

        return [
            'refreshed_at' => now()->toIso8601String(),
            'kpis' => [
                'total_campaigns' => $campaigns->count(),
                'active_campaigns' => $campaigns
                    ->filter(fn (LaunchVoucherCampaign $campaign) => $campaign->active && ! $campaign->isExpired())
                    ->count(),
                'expired_campaigns' => $campaigns->filter(fn (LaunchVoucherCampaign $campaign) => $campaign->isExpired())->count(),
                'shared_campaigns' => $campaigns->filter(fn (LaunchVoucherCampaign $campaign) => $campaign->isSharedCode())->count(),
                'unique_campaigns' => $campaigns->filter(fn (LaunchVoucherCampaign $campaign) => $campaign->isUniqueCodes())->count(),
                'generated_codes' => $vouchers->count(),
                'successful_redemptions' => LaunchVoucherRedemption::query()
                    ->where('status', LaunchVoucherRedemption::STATUS_REDEEMED)
                    ->count(),
                'remaining_capacity' => $remainingCapacity,
                'blocked_attempts' => MarketingEvent::query()
                    ->where('event_type', MarketingEventService::TYPE_VOUCHER_BLOCKED)
                    ->count(),
                'expired_reservations' => LaunchVoucherRedemption::query()
                    ->where('status', LaunchVoucherRedemption::STATUS_EXPIRED)
                    ->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function campaignDetail(LaunchVoucherCampaign $campaign): array
    {
        $campaign->load('vouchers');
        $capacity = $this->campaignCapacityService->snapshot($campaign);
        $statusCounts = LaunchVoucherRedemption::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $usedCapacity = (int) ($statusCounts[LaunchVoucherRedemption::STATUS_RESERVED] ?? 0)
            + (int) ($statusCounts[LaunchVoucherRedemption::STATUS_REDEEMED] ?? 0);
        $totalCapacity = $campaign->isSharedCode()
            ? (int) $campaign->max_redemptions
            : (int) $campaign->generated_count;
        $progressPct = $totalCapacity > 0 ? round(($usedCapacity / $totalCapacity) * 100, 2) : 0;

        return [
            'campaign' => $this->opsMarketingService->presentCampaignPublic($campaign),
            'capacity' => $capacity,
            'statistics' => [
                'reserved' => (int) ($statusCounts[LaunchVoucherRedemption::STATUS_RESERVED] ?? 0),
                'redeemed' => (int) ($statusCounts[LaunchVoucherRedemption::STATUS_REDEEMED] ?? 0),
                'released' => (int) ($statusCounts[LaunchVoucherRedemption::STATUS_RELEASED] ?? 0),
                'expired' => (int) ($statusCounts[LaunchVoucherRedemption::STATUS_EXPIRED] ?? 0),
                'cancelled' => (int) ($statusCounts[LaunchVoucherRedemption::STATUS_CANCELLED] ?? 0),
                'used_capacity' => $usedCapacity,
                'total_capacity' => $totalCapacity,
                'progress_pct' => $progressPct,
            ],
            'restrictions' => [
                'one_per_phone' => $campaign->one_per_phone,
                'one_per_email' => $campaign->one_per_email,
                'one_per_device' => $campaign->one_per_device,
                'reservation_timeout_minutes' => $campaign->reservation_timeout_minutes,
            ],
            'vouchers' => $campaign->vouchers
                ->map(fn (LaunchVoucher $voucher) => $this->opsMarketingService->presentVoucherPublic($voucher))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function redemptionLog(array $filters): LengthAwarePaginator
    {
        $query = LaunchVoucherRedemption::query()
            ->with([
                'voucher:id,code,name,campaign_id',
                'campaign:id,name,distribution_mode',
                'transaction:id,reference,status,customer_phone,customer_email,product_amount',
            ]);

        $this->applyRedemptionFilters($query, $filters);

        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'status', 'reserved_at', 'redeemed_at', 'discount_amount'];

        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        return $query
            ->orderBy($sortBy, $sortDir)
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function abuseMonitoring(array $filters = []): array
    {
        $days = max(1, min(90, (int) ($filters['days'] ?? 14)));
        $since = now()->subDays($days);

        $blockedByReason = MarketingEvent::query()
            ->where('event_type', MarketingEventService::TYPE_VOUCHER_BLOCKED)
            ->where('occurred_at', '>=', $since)
            ->get()
            ->groupBy(fn (MarketingEvent $event) => (string) data_get($event->metadata, 'reason', 'unknown'))
            ->map(fn ($events) => $events->count());

        $rejectedByCode = MarketingEvent::query()
            ->where('event_type', MarketingEventService::TYPE_VOUCHER_REJECTED)
            ->where('occurred_at', '>=', $since)
            ->get()
            ->groupBy(fn (MarketingEvent $event) => (string) data_get($event->metadata, 'code', 'unknown'))
            ->map(fn ($events) => $events->count());

        $expiredReservations = LaunchVoucherRedemption::query()
            ->where('status', LaunchVoucherRedemption::STATUS_EXPIRED)
            ->where('updated_at', '>=', $since)
            ->count();

        return [
            'window_days' => $days,
            'summary' => [
                'phone_blocked' => (int) ($blockedByReason['phone'] ?? 0),
                'device_blocked' => (int) ($blockedByReason['device'] ?? 0),
                'email_blocked' => (int) ($blockedByReason['email'] ?? 0),
                'invalid_voucher' => (int) ($rejectedByCode['VOUCHER_NOT_FOUND'] ?? 0),
                'expired_voucher' => (int) ($rejectedByCode['VOUCHER_EXPIRED'] ?? 0),
                'capacity_exceeded' => (int) (($rejectedByCode['VOUCHER_CAMPAIGN_EXHAUSTED'] ?? 0) + ($rejectedByCode['VOUCHER_EXHAUSTED'] ?? 0)),
                'reservation_expired' => $expiredReservations,
            ],
            'blocked_trend' => $this->blockedTrend($since),
            'recent_events' => $this->recentAbuseEvents($since, (int) ($filters['limit'] ?? 50)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        $since = now()->subDays(13)->startOfDay();

        $dailyRedemptions = LaunchVoucherRedemption::query()
            ->where('status', LaunchVoucherRedemption::STATUS_REDEEMED)
            ->where('redeemed_at', '>=', $since)
            ->selectRaw('DATE(redeemed_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $campaignUsage = LaunchVoucherCampaign::query()
            ->orderByDesc('redeemed_count')
            ->limit(10)
            ->get(['id', 'name', 'distribution_mode', 'redeemed_count', 'generated_count', 'max_redemptions'])
            ->map(fn (LaunchVoucherCampaign $campaign) => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'distribution_mode' => $campaign->distribution_mode,
                'redeemed_count' => $campaign->redeemed_count,
                'capacity' => $campaign->isSharedCode() ? $campaign->max_redemptions : $campaign->generated_count,
            ])
            ->all();

        $networkDistribution = LaunchVoucherCampaign::query()
            ->selectRaw("COALESCE(NULLIF(network, ''), 'All') as network_label, COUNT(*) as total")
            ->groupBy('network_label')
            ->pluck('total', 'network_label');

        return [
            'daily_redemptions' => $this->fillDailySeries($since, $dailyRedemptions),
            'campaign_usage' => $campaignUsage,
            'network_distribution' => collect($networkDistribution)
                ->map(fn ($total, $label) => ['label' => $label, 'value' => (int) $total])
                ->values()
                ->all(),
            'blocked_trend' => $this->blockedTrend($since),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerLookup(string $query): array
    {
        $needle = trim($query);

        if ($needle === '') {
            return ['query' => '', 'redemptions' => [], 'transactions' => []];
        }

        $normalizedPhone = VoucherIdentityNormalizer::normalizePhone($needle);
        $normalizedCode = $this->codeGenerator->normalize($needle);

        $redemptionQuery = LaunchVoucherRedemption::query()
            ->with(['voucher:id,code,name', 'campaign:id,name', 'transaction:id,reference,status,customer_phone,product_amount,payable_amount'])
            ->where(function (Builder $builder) use ($needle, $normalizedPhone, $normalizedCode): void {
                $builder
                    ->where('customer_phone', 'like', '%'.$needle.'%')
                    ->orWhere('customer_phone_normalized', $normalizedPhone)
                    ->orWhereHas('transaction', fn (Builder $inner) => $inner->where('reference', $needle))
                    ->orWhereHas('voucher', function (Builder $inner) use ($needle, $normalizedCode): void {
                        $inner
                            ->where('code', 'like', '%'.$needle.'%')
                            ->orWhere('code_normalized', $normalizedCode);
                    });
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $transactionQuery = Transaction::query()
            ->where(function (Builder $builder) use ($needle, $normalizedPhone, $normalizedCode): void {
                $builder
                    ->where('reference', $needle)
                    ->orWhere('customer_phone', 'like', '%'.$needle.'%')
                    ->orWhere('voucher_code', 'like', '%'.$needle.'%');

                if ($normalizedCode !== '') {
                    $builder->orWhere('voucher_code', 'like', '%'.$normalizedCode.'%');
                }

                if ($normalizedPhone !== '') {
                    $builder->orWhere('customer_phone', $normalizedPhone);
                }
            })
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'reference', 'status', 'customer_phone', 'product_amount', 'payable_amount', 'voucher_code', 'voucher_discount_amount', 'created_at']);

        return [
            'query' => $needle,
            'redemptions' => $redemptionQuery->map(fn (LaunchVoucherRedemption $redemption) => $this->presentRedemption($redemption))->all(),
            'transactions' => $transactionQuery->map(fn (Transaction $transaction) => [
                'reference' => $transaction->reference,
                'status' => $transaction->status->value ?? (string) $transaction->status,
                'customer_phone' => $transaction->customer_phone,
                'product_amount' => $transaction->product_amount,
                'payable_amount' => $transaction->payable_amount,
                'voucher_code' => $transaction->voucher_code,
                'voucher_discount_amount' => $transaction->voucher_discount_amount,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function extendExpiry(LaunchVoucherCampaign $campaign, Carbon $expiresAt): array
    {
        $campaign->update(['expires_at' => $expiresAt]);
        LaunchVoucher::query()
            ->where('campaign_id', $campaign->id)
            ->update(['expires_at' => $expiresAt]);

        return $this->campaignDetail($campaign->fresh('vouchers'));
    }

    /**
     * @return array<string, mixed>
     */
    public function increaseCapacity(LaunchVoucherCampaign $campaign, int $maxRedemptions): array
    {
        if (! $campaign->isSharedCode()) {
            throw new \InvalidArgumentException('Capacity increases are only supported for shared campaigns.');
        }

        if ($maxRedemptions < (int) $campaign->max_redemptions) {
            throw new \InvalidArgumentException('New capacity must be greater than or equal to the current maximum.');
        }

        $campaign->update(['max_redemptions' => $maxRedemptions]);
        LaunchVoucher::query()
            ->where('campaign_id', $campaign->id)
            ->update(['max_redemptions' => $maxRedemptions]);

        return $this->campaignDetail($campaign->fresh('vouchers'));
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRedemption(LaunchVoucherRedemption $redemption): array
    {
        return [
            'id' => $redemption->id,
            'campaign_id' => $redemption->campaign_id,
            'campaign_name' => $redemption->campaign?->name,
            'distribution_mode' => $redemption->campaign?->distribution_mode,
            'voucher_code' => $redemption->voucher?->code,
            'voucher_name' => $redemption->voucher?->name,
            'reference' => $redemption->transaction?->reference,
            'transaction_status' => $redemption->transaction?->status->value ?? $redemption->transaction?->status,
            'status' => $redemption->status,
            'discount_amount' => $redemption->discount_amount,
            'customer_phone' => $redemption->customer_phone,
            'customer_phone_normalized' => $redemption->customer_phone_normalized,
            'customer_email' => $redemption->customer_email,
            'device_id' => $redemption->device_id,
            'reserved_at' => $redemption->reserved_at?->toIso8601String(),
            'redeemed_at' => $redemption->redeemed_at?->toIso8601String(),
            'released_at' => $redemption->released_at?->toIso8601String(),
            'created_at' => $redemption->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Builder<LaunchVoucherRedemption>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyRedemptionFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $normalizedCode = $this->codeGenerator->normalize($search);
            $normalizedPhone = VoucherIdentityNormalizer::normalizePhone($search);

            $query->where(function (Builder $builder) use ($search, $normalizedCode, $normalizedPhone): void {
                $builder
                    ->where('customer_phone', 'like', '%'.$search.'%')
                    ->orWhere('customer_phone_normalized', $normalizedPhone)
                    ->orWhereHas('transaction', fn (Builder $inner) => $inner->where('reference', 'like', '%'.$search.'%'))
                    ->orWhereHas('voucher', function (Builder $inner) use ($search, $normalizedCode): void {
                        $inner
                            ->where('code', 'like', '%'.$search.'%')
                            ->orWhere('code_normalized', $normalizedCode);
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', (int) $filters['campaign_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse((string) $filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse((string) $filters['date_to'])->endOfDay());
        }
    }

    /**
     * @return list<array{date: string, total: int}>
     */
    private function blockedTrend(Carbon $since): array
    {
        $rows = MarketingEvent::query()
            ->where('event_type', MarketingEventService::TYPE_VOUCHER_BLOCKED)
            ->where('occurred_at', '>=', $since)
            ->selectRaw('DATE(occurred_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        return $this->fillDailySeries($since, $rows);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, mixed>  $rows
     * @return list<array{date: string, total: int}>
     */
    private function fillDailySeries(Carbon $since, $rows): array
    {
        $series = [];
        $cursor = $since->copy()->startOfDay();
        $end = now()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $series[] = [
                'date' => $key,
                'total' => (int) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentAbuseEvents(Carbon $since, int $limit): array
    {
        return MarketingEvent::query()
            ->with('voucher:id,code,name')
            ->whereIn('event_type', [
                MarketingEventService::TYPE_VOUCHER_BLOCKED,
                MarketingEventService::TYPE_VOUCHER_REJECTED,
            ])
            ->where('occurred_at', '>=', $since)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (MarketingEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'voucher_code' => $event->voucher?->code,
                'reference' => $event->reference,
                'metadata' => $event->metadata,
                'actor' => $event->actor,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ])
            ->all();
    }
}
