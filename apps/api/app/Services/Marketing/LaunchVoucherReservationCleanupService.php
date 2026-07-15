<?php

namespace App\Services\Marketing;

use App\Enums\TransactionStatus;
use App\Models\LaunchVoucherCampaign;
use App\Models\LaunchVoucherRedemption;
use Illuminate\Support\Facades\DB;

class LaunchVoucherReservationCleanupService
{
    public function releaseExpiredReservations(): int
    {
        $released = 0;

        $candidates = LaunchVoucherRedemption::query()
            ->with(['transaction', 'campaign'])
            ->where('status', LaunchVoucherRedemption::STATUS_RESERVED)
            ->whereNotNull('reserved_at')
            ->get();

        foreach ($candidates as $redemption) {
            $campaign = $redemption->campaign;
            $timeoutMinutes = (int) ($campaign?->reservation_timeout_minutes ?? 30);
            $expiresAt = $redemption->reserved_at?->copy()->addMinutes($timeoutMinutes);

            if ($expiresAt === null || $expiresAt->isFuture()) {
                continue;
            }

            $transaction = $redemption->transaction;
            if ($transaction && in_array($transaction->status, [
                TransactionStatus::PAYMENT_SUCCESS,
                TransactionStatus::FULFILLMENT_PENDING,
                TransactionStatus::FULFILLED,
            ], true)) {
                continue;
            }

            DB::transaction(function () use ($redemption): void {
                $locked = LaunchVoucherRedemption::query()
                    ->where('id', $redemption->id)
                    ->where('status', LaunchVoucherRedemption::STATUS_RESERVED)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    return;
                }

                $locked->update([
                    'status' => LaunchVoucherRedemption::STATUS_EXPIRED,
                    'released_at' => now(),
                ]);
            });

            $released++;
        }

        return $released;
    }
}
