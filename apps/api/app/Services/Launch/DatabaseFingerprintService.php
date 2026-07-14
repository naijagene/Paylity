<?php

namespace App\Services\Launch;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseFingerprintService
{
    /**
     * @return array<string, mixed>
     */
    public function fingerprint(): array
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}", []);
        $driver = (string) ($config['driver'] ?? 'unknown');
        $databaseName = (string) ($config['database'] ?? '');
        $path = $driver === 'sqlite' ? $databaseName : null;

        return [
            'connection' => $connection,
            'driver' => $driver,
            'database_name' => $driver === 'sqlite' ? null : $databaseName,
            'database_path' => $path,
            'transaction_count' => Schema::hasTable('transactions') ? Transaction::query()->count() : 0,
            'migration_status' => $this->migrationStatus(),
            'writable' => $this->isWritable($driver, $databaseName),
            'schema_version' => (string) config('app.version'),
            'build_identifier' => (string) config('app.build'),
        ];
    }

    /**
     * @return array{pending: int, ran: int}
     */
    private function migrationStatus(): array
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = $migrator->getRepository()->getRan();

            return [
                'pending' => count(array_diff(array_keys($files), $ran)),
                'ran' => count($ran),
            ];
        } catch (\Throwable) {
            return ['pending' => -1, 'ran' => -1];
        }
    }

    private function isWritable(string $driver, string $databaseName): bool
    {
        if ($driver === 'sqlite') {
            if ($databaseName === '' || ! file_exists($databaseName)) {
                return is_writable(dirname($databaseName !== '' ? $databaseName : database_path()));
            }

            return is_writable($databaseName) && is_writable(dirname($databaseName));
        }

        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
