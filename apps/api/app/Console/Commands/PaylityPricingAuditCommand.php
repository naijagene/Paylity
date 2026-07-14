<?php

namespace App\Console\Commands;

use App\Services\Launch\PricingAuditService;
use Illuminate\Console\Command;

class PaylityPricingAuditCommand extends Command
{
    protected $signature = 'paylity:pricing-audit
                            {--product=airtime : Product type}
                            {--amount= : Audit a single amount}
                            {--json}';

    protected $description = 'Audit launch pricing margins for standard product amounts';

    public function __construct(
        private readonly PricingAuditService $pricingAuditService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->pricingAuditService->audit(
            product: (string) $this->option('product'),
            amount: $this->option('amount') !== null ? (int) $this->option('amount') : null,
        );

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->info('Pricing audit — negative margins: '.$report['negative_margin_count']);
            $this->table(
                ['Amount', 'Convenience', 'Gateway', 'Payable', 'Margin ₦', 'Status'],
                collect($report['amounts'])->map(fn (array $row) => [
                    $row['product_amount'],
                    $row['convenience_fee'],
                    $row['gateway_fee'],
                    $row['payable_amount'],
                    (int) round($row['estimated_gross_margin_kobo'] / 100),
                    $row['status'],
                ])->all(),
            );
        }

        return ($report['all_positive'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
