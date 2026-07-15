<?php

namespace App\Services\Marketing;

use App\Enums\TransactionStatus;
use App\Models\LaunchVoucherCampaign;
use App\Models\LaunchVoucherRedemption;
use Illuminate\Support\Collection;

class LaunchVoucherCampaignCapacityService
{
    /**
     * @return array{
     *     reserved: int,
     *     redeemed: int,
     *     released: int,
     *     expired: int,
     *     blocked_attempts: int,
     *     remaining_capacity: int,
     *     maximum_redemptions: int|null,
     *     unused_codes: int
     * }
     */
    public function snapshot(LaunchVoucherCampaign $campaign): array
    {
        $statusCounts = LaunchVoucherRedemption::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $reserved = (int) ($statusCounts[LaunchVoucherRedemption::STATUS_RESERVED] ?? 0);
        $redeemed = (int) ($statusCounts[LaunchVoucherRedemption::STATUS_REDEEMED] ?? 0);
        $released = (int) ($statusCounts[LaunchVoucherRedemption::STATUS_RELEASED] ?? 0);
        $expired = (int) ($statusCounts[LaunchVoucherRedemption::STATUS_EXPIRED] ?? 0);

        $unusedCodes = $campaign->vouchers()
            ->where('active', true)
            ->get()
            ->filter(fn ($voucher) => ! $voucher->hasReservationOrRedemption())
            ->count();

        return [
            'reserved' => $reserved,
            'redeemed' => $redeemed,
            'released' => $released,
            'expired' => $expired,
            'blocked_attempts' => 0,
            'remaining_capacity' => $this->remainingCapacity($campaign, $reserved, $unusedCodes),
            'maximum_redemptions' => $campaign->isSharedCode() ? $campaign->max_redemptions : null,
            'unused_codes' => $unusedCodes,
        ];
    }

    public function remainingCapacity(LaunchVoucherCampaign $campaign, ?int $reservedCount = null, ?int $unusedCodes = null): int
    {
        if ($campaign->isSharedCode()) {
            $reservedCount ??= $this->activeReservedCount($campaign->id);
            $redeemedCount = (int) $campaign->redeemed_count;

            return max(0, (int) $campaign->max_redemptions - $reservedCount - $redeemedCount);
        }

        return $unusedCodes ?? $campaign->vouchers()
            ->where('active', true)
            ->get()
            ->filter(fn ($voucher) => ! $voucher->hasReservationOrRedemption())
            ->count();
    }

    public function activeReservedCount(int $campaignId): int
    {
        return LaunchVoucherRedemption::query()
            ->where('campaign_id', $campaignId)
            ->where('status', LaunchVoucherRedemption::STATUS_RESERVED)
            ->whereHas('transaction', function ($query): void {
                $query->whereNotIn('status', [
                    TransactionStatus::PAYMENT_FAILED,
                    TransactionStatus::FAILED,
                    TransactionStatus::CANCELLED,
                ]);
            })
            ->count();
    }

    public function assertCapacityAvailable(LaunchVoucherCampaign $campaign): void
    {
        if ($this->remainingCapacity($campaign) <= 0) {
            throw new \App\Exceptions\LaunchVoucherException(
                'This voucher campaign has no remaining capacity.',
                'VOUCHER_CAMPAIGN_EXHAUSTED',
            );
        }
    }
}
