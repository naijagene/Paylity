<?php

namespace App\Console\Commands;

use App\Services\Launch\DatabaseFingerprintService;
use Illuminate\Console\Command;

class PaylityDatabaseFingerprintCommand extends Command
{
    protected $signature = 'paylity:database-fingerprint {--json : Output JSON only}';

    protected $description = 'Print safe database fingerprint information';

    public function __construct(
        private readonly DatabaseFingerprintService $databaseFingerprintService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $fingerprint = $this->databaseFingerprintService->fingerprint();

        if ($this->option('json')) {
            $this->line(json_encode($fingerprint, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(
            ['Field', 'Value'],
            collect($fingerprint)->map(fn ($value, $key) => [$key, is_array($value) ? json_encode($value) : (string) $value])->values()->all(),
        );

        return self::SUCCESS;
    }
}
