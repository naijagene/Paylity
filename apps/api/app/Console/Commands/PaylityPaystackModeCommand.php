<?php

namespace App\Console\Commands;

use App\Services\Launch\PaystackModeInspector;
use Illuminate\Console\Command;

class PaylityPaystackModeCommand extends Command
{
    protected $signature = 'paylity:paystack-mode {--json}';

    protected $description = 'Inspect Paystack configuration mode without exposing secrets';

    public function __construct(
        private readonly PaystackModeInspector $paystackModeInspector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->paystackModeInspector->inspect();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $rows = [
                ['detected_mode', $report['detected_mode'] ?? 'unknown'],
                ['public_key_mode', $report['public_key_mode'] ?? 'unknown'],
                ['secret_key_mode', $report['secret_key_mode'] ?? 'unknown'],
                ['public_key_prefix', $report['public_key_prefix'] ?? '—'],
                ['secret_key_prefix', $report['secret_key_prefix'] ?? '—'],
                ['configuration_complete', ($report['configuration_complete'] ?? false) ? 'yes' : 'no'],
                ['callback_url', $report['callback_url'] ?? ''],
                ['webhook_url', $report['webhook_url'] ?? ''],
                ['environment', $report['environment'] ?? ''],
                ['launch_mode', $report['launch_mode'] ?? ''],
                ['verdict', $report['verdict'] ?? 'invalid'],
            ];

            $this->table(['Field', 'Value'], $rows);

            $blockers = $report['blockers'] ?? [];
            if ($blockers !== []) {
                $this->newLine();
                $this->error('Blockers:');
                foreach ($blockers as $blocker) {
                    $this->line('- '.$blocker);
                }
            }
        }

        return ($report['verdict'] ?? PaystackModeInspector::VERDICT_INVALID) === PaystackModeInspector::VERDICT_VALID
            ? self::SUCCESS
            : self::FAILURE;
    }
}
