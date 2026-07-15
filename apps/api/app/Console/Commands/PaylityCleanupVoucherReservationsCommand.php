<?php

namespace App\Console\Commands;

use App\Services\Marketing\LaunchVoucherReservationCleanupService;
use Illuminate\Console\Command;

class PaylityCleanupVoucherReservationsCommand extends Command
{
    protected $signature = 'paylity:cleanup-voucher-reservations';

    protected $description = 'Release expired abandoned launch voucher reservations.';

    public function handle(LaunchVoucherReservationCleanupService $service): int
    {
        $released = $service->releaseExpiredReservations();
        $this->info("Released {$released} expired voucher reservation(s).");

        return self::SUCCESS;
    }
}
