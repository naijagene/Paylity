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
            $this->table(['Field', 'Value'], collect($report)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value])->all());
        }

        return self::SUCCESS;
    }
}
