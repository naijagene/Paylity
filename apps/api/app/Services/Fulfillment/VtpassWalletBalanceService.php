<?php

namespace App\Services\Fulfillment;

use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Facades\Cache;

class VtpassWalletBalanceService
{
    public const HEALTH_HEALTHY = 'healthy';

    public const HEALTH_WARNING = 'warning';

    public const HEALTH_CRITICAL = 'critical';

    public const HEALTH_UNKNOWN = 'unknown';

    private const CACHE_KEY = 'vtpass.wallet.balance';

    private const STATS_PREFIX = 'vtpass.wallet.stats.';

    public function __construct(
        private readonly VTPassService $vtpassService,
        private readonly SystemSettingsService $systemSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        if (! $forceRefresh && ($cached = Cache::get(self::CACHE_KEY)) !== null) {
            return array_merge($cached, ['cached' => true]);
        }

        $payload = $this->fetchAndEnrich();
        Cache::put(self::CACHE_KEY, $payload, $this->refreshSeconds());

        return array_merge($payload, ['cached' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        return $this->snapshot(true);
    }

    /**
     * @return array<string, mixed>
     */
    public function dailyStats(?string $date = null): array
    {
        $date ??= today()->toDateString();
        $stats = Cache::get(self::STATS_PREFIX.$date, [
            'date' => $date,
            'opening_balance' => null,
            'closing_balance' => null,
            'lowest_balance' => null,
            'highest_balance' => null,
            'readings' => 0,
            'last_checked_at' => null,
            'recharge_events' => [],
        ]);

        return array_merge($stats, [
            'recharge_events_available' => false,
            'recharge_events_note' => 'VTPass recharge event history is not exposed by the provider API yet.',
        ]);
    }

    public function refreshSeconds(): int
    {
        return max(15, $this->systemSettings->getInt(
            SystemSettingKeys::WALLET_REFRESH_SECONDS,
            60,
        ));
    }

    public function lowThreshold(): int
    {
        return max(0, $this->systemSettings->getInt(
            SystemSettingKeys::WALLET_LOW_BALANCE_THRESHOLD,
            500_000,
        ));
    }

    public function criticalThreshold(): int
    {
        return max(0, $this->systemSettings->getInt(
            SystemSettingKeys::WALLET_CRITICAL_BALANCE_THRESHOLD,
            100_000,
        ));
    }

    /**
     * @param  array<string, mixed>  $balance
     */
    public function classifyHealth(array $balance): string
    {
        if (($balance['available'] ?? false) !== true || $balance['balance'] === null) {
            return self::HEALTH_UNKNOWN;
        }

        $amount = (float) $balance['balance'];
        $critical = $this->criticalThreshold();
        $low = $this->lowThreshold();

        if ($amount < $critical) {
            return self::HEALTH_CRITICAL;
        }

        if ($amount < $low) {
            return self::HEALTH_WARNING;
        }

        return self::HEALTH_HEALTHY;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAndEnrich(): array
    {
        $raw = $this->vtpassService->checkBalance();
        $checkedAt = now()->toIso8601String();

        $payload = array_merge($raw, [
            'health' => $this->classifyHealth($raw),
            'checked_at' => $checkedAt,
            'low_threshold' => $this->lowThreshold(),
            'critical_threshold' => $this->criticalThreshold(),
            'refresh_seconds' => $this->refreshSeconds(),
        ]);

        $this->recordDailyStats($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordDailyStats(array $payload): void
    {
        if (($payload['available'] ?? false) !== true || $payload['balance'] === null) {
            return;
        }

        $date = today()->toDateString();
        $key = self::STATS_PREFIX.$date;
        $balance = (float) $payload['balance'];

        $stats = Cache::get($key, [
            'date' => $date,
            'opening_balance' => null,
            'closing_balance' => null,
            'lowest_balance' => null,
            'highest_balance' => null,
            'readings' => 0,
            'last_checked_at' => null,
            'recharge_events' => [],
        ]);

        if ($stats['opening_balance'] === null) {
            $stats['opening_balance'] = $balance;
        }

        $stats['closing_balance'] = $balance;
        $stats['lowest_balance'] = $stats['lowest_balance'] === null
            ? $balance
            : min((float) $stats['lowest_balance'], $balance);
        $stats['highest_balance'] = $stats['highest_balance'] === null
            ? $balance
            : max((float) $stats['highest_balance'], $balance);
        $stats['readings'] = (int) $stats['readings'] + 1;
        $stats['last_checked_at'] = $payload['checked_at'];

        Cache::put($key, $stats, now()->endOfDay()->addDay());
    }
}
