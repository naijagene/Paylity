<?php

namespace App\Services\Launch;

use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DatabaseBackupService
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(): array
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            throw new \RuntimeException('Automated backup command currently supports SQLite only. Use mysqldump for MySQL.');
        }

        $databasePath = (string) config("database.connections.{$connection}.database");

        if ($databasePath === '' || ! file_exists($databasePath)) {
            throw new \RuntimeException('SQLite database file does not exist.');
        }

        $backupDir = storage_path('app/backups/database');
        File::ensureDirectoryExists($backupDir);

        $timestamp = now()->format('Ymd_His');
        $filename = "paylity-sqlite-{$timestamp}.sqlite";
        $destination = $backupDir.DIRECTORY_SEPARATOR.$filename;

        if (! $this->sqliteBackup($databasePath, $destination)) {
            throw new \RuntimeException('SQLite backup failed.');
        }

        $size = filesize($destination) ?: 0;

        if ($size <= 0) {
            @unlink($destination);
            throw new \RuntimeException('Backup file is zero bytes and was rejected.');
        }

        $checksum = hash_file('sha256', $destination) ?: '';
        $manifest = [
            'created_at' => now()->toIso8601String(),
            'connection' => $connection,
            'driver' => $driver,
            'source_path' => $databasePath,
            'backup_path' => $destination,
            'size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'version' => (string) config('app.version'),
            'build' => (string) config('app.build'),
        ];

        file_put_contents($destination.'.manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $this->settings->set(SystemSettingKeys::BACKUP_LAST_RUN_AT, now()->toIso8601String());
        $this->settings->set(SystemSettingKeys::BACKUP_LAST_PATH, $destination);
        $this->settings->set(SystemSettingKeys::BACKUP_LAST_CHECKSUM, $checksum);

        $this->enforceRetention($backupDir);

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(?string $file = null): array
    {
        $path = $file ?: $this->settings->getString(SystemSettingKeys::BACKUP_LAST_PATH);

        if ($path === '' || ! file_exists($path)) {
            throw new \RuntimeException('Backup file not found.');
        }

        $size = filesize($path) ?: 0;

        if ($size <= 0) {
            throw new \RuntimeException('Backup file is zero bytes.');
        }

        if (Str::contains($path, [public_path(), 'public'.DIRECTORY_SEPARATOR])) {
            throw new \RuntimeException('Backup must not be stored in a public directory.');
        }

        $checksum = hash_file('sha256', $path) ?: '';
        $expected = $this->settings->getString(SystemSettingKeys::BACKUP_LAST_CHECKSUM);
        $matches = $expected === '' || hash_equals($expected, $checksum);

        if ($matches) {
            $this->settings->set(SystemSettingKeys::BACKUP_LAST_VERIFIED_AT, now()->toIso8601String());
        }

        return [
            'path' => $path,
            'size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'matches_recorded_checksum' => $matches,
            'verified_at' => $matches ? now()->toIso8601String() : null,
        ];
    }

    private function sqliteBackup(string $source, string $destination): bool
    {
        $pdo = new \PDO('sqlite:'.$source);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA wal_checkpoint(FULL);');

        return copy($source, $destination);
    }

    private function enforceRetention(string $backupDir, int $keep = 14): void
    {
        $files = collect(File::files($backupDir))
            ->filter(fn (\SplFileInfo $file) => str_ends_with($file->getFilename(), '.sqlite'))
            ->sortByDesc(fn (\SplFileInfo $file) => $file->getMTime())
            ->values();

        foreach ($files->slice($keep) as $oldFile) {
            @unlink($oldFile->getPathname());
            @unlink($oldFile->getPathname().'.manifest.json');
        }
    }
}
