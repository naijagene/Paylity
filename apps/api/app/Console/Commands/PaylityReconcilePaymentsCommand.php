<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentReconciliationService;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Console\Command;

class PaylityReconcilePaymentsCommand extends Command
{
    protected $signature = 'paylity:reconcile-payments';

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
            $this->systemSettings->getInt(SystemSettingKeys::PAYMENT_RECONCILE_STALE_MINUTES, 15),
        );
        $fulfillmentStaleMinutes = max(
            5,
            $this->systemSettings->getInt(SystemSettingKeys::FULFILLMENT_STALE_MINUTES, 30),
        );

        $summary = $this->paymentReconciliationService->reconcile(
            $paymentStaleMinutes,
            $fulfillmentStaleMinutes,
        );

        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn (int $count, string $metric) => [$metric, $count])->values()->all(),
        );

        return self::SUCCESS;
    }
}
