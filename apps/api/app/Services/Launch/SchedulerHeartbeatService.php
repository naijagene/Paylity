<?php

namespace App\Services\Launch;

use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class SchedulerHeartbeatService
{
    public const STATUS_HEALTHY = 'healthy';

    public const STATUS_WARNING = 'warning';

    public const STATUS_CRITICAL = 'critical';

    public const STATUS_UNKNOWN = 'unknown';

    private const CACHE_KEY = 'paylity.scheduler.last_run_at';

    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function record(?Carbon $timestamp = null): void
    {
        $at = ($timestamp ?? now())->toIso8601String();
        Cache::forever(self::CACHE_KEY, $at);
        $this->settings->set(SystemSettingKeys::SCHEDULER_LAST_RUN_AT, $at);
    }

    public function lastRunAt(): ?Carbon
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return Carbon::parse($cached);
        }

        $stored = $this->settings->getString(SystemSettingKeys::SCHEDULER_LAST_RUN_AT);

        return $stored !== '' ? Carbon::parse($stored) : null;
    }

    /**
     * @return array{status: string, last_run_at: string|null, age_seconds: int|null}
     */
    public function snapshot(): array
    {
        $lastRun = $this->lastRunAt();
        $ageSeconds = $lastRun?->diffInSeconds(now());

        return [
            'status' => $this->statusForAge($ageSeconds),
            'last_run_at' => $lastRun?->toIso8601String(),
            'age_seconds' => $ageSeconds,
        ];
    }

    public function statusForAge(?int $ageSeconds): string
    {
        if ($ageSeconds === null) {
            return self::STATUS_UNKNOWN;
        }

        if ($ageSeconds <= 120) {
            return self::STATUS_HEALTHY;
        }

        if ($ageSeconds <= 300) {
            return self::STATUS_WARNING;
        }

        return self::STATUS_CRITICAL;
    }
}
