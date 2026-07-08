<?php

namespace App\Console\Commands;

use App\Models\OtpVerification;
use Illuminate\Console\Command;

class PaylityCleanupOtpCommand extends Command
{
    protected $signature = 'paylity:cleanup-otp {--days=30 : Delete OTP records older than this many days}';

    protected $description = 'Purge expired OTP verification records';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = OtpVerification::query()
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deleted} OTP verification record(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
