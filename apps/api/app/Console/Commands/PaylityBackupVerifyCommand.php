<?php

namespace App\Console\Commands;

use App\Services\Launch\DatabaseBackupService;
use Illuminate\Console\Command;

class PaylityBackupVerifyCommand extends Command
{
    protected $signature = 'paylity:backup-verify {--file= : Backup file path}';

    protected $description = 'Verify a database backup checksum and size';

    public function __construct(
        private readonly DatabaseBackupService $databaseBackupService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->databaseBackupService->verify(
            $this->option('file') ? (string) $this->option('file') : null,
        );

        $this->table(['Field', 'Value'], collect($result)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value])->all());

        return ($result['matches_recorded_checksum'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
