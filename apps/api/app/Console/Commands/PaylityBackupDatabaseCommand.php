<?php

namespace App\Console\Commands;

use App\Services\Launch\DatabaseBackupService;
use Illuminate\Console\Command;

class PaylityBackupDatabaseCommand extends Command
{
    protected $signature = 'paylity:backup-database';

    protected $description = 'Create a timestamped SQLite database backup';

    public function __construct(
        private readonly DatabaseBackupService $databaseBackupService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $manifest = $this->databaseBackupService->create();
        $this->info('Backup created: '.$manifest['backup_path']);
        $this->line('Checksum: '.$manifest['checksum_sha256']);

        return self::SUCCESS;
    }
}
