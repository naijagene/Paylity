<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentReconciliationService;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PaylityReconcilePaymentsCommand extends Command
{
    protected $signature = 'paylity:reconcile-payments
                            {--reference= : Reconcile a single transaction reference}
                            {--since= : Only transactions created after this ISO date}
                            {--limit=50 : Maximum transactions to inspect}
                            {--dry-run : Inspect without making changes}
                            {--repair : Apply safe repairs (default unless --dry-run)}';

    protected $description = 'Reconcile stale Paystack payments and fulfillment states';

    public function __construct(
        private readonly PaymentReconciliationService $paymentReconciliationService,
        private readonly SystemSettingsService $systemSettings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $paymentStaleMinutes = max(
            5,
            $this->systemSettings->getInt(SystemSettingKeys::PAYMENT_PENDING_STALE_MINUTES,
                $this->systemSettings->getInt(SystemSettingKeys::PAYMENT_RECONCILE_STALE_MINUTES, 15),
            ),
        );
        $fulfillmentStaleMinutes = max(
            5,
            $this->systemSettings->getInt(SystemSettingKeys::FULFILLMENT_PROCESSING_STALE_MINUTES,
                $this->systemSettings->getInt(SystemSettingKeys::FULFILLMENT_STALE_MINUTES, 30),
            ),
        );

        $dryRun = (bool) $this->option('dry-run');
        $repair = ! $dryRun;
        $since = $this->option('since')
            ? Carbon::parse((string) $this->option('since'))
            : null;

        $summary = $this->paymentReconciliationService->reconcile(
            paymentStaleMinutes: $paymentStaleMinutes,
            fulfillmentStaleMinutes: $fulfillmentStaleMinutes,
            reference: $this->option('reference') ? (string) $this->option('reference') : null,
            since: $since,
            limit: max(1, (int) $this->option('limit')),
            dryRun: $dryRun,
            repair: $repair,
        );

        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn (int $count, string $metric) => [$metric, $count])->values()->all(),
        );

        if ($dryRun) {
            $this->info('Dry run — no changes were applied.');
        }

        return self::SUCCESS;
    }
}
