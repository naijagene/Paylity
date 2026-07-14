<?php

namespace App\Console\Commands;

use App\Services\Launch\VtpassModeInspector;
use Illuminate\Console\Command;

class PaylityVtpassModeCommand extends Command
{
    protected $signature = 'paylity:vtpass-mode {--json}';

    protected $description = 'Inspect VTPass configuration mode without exposing secrets';

    public function __construct(
        private readonly VtpassModeInspector $vtpassModeInspector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->vtpassModeInspector->inspect();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->table(['Field', 'Value'], collect($report)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value])->all());
        }

        return self::SUCCESS;
    }
}
