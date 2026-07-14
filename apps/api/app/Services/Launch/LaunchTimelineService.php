<?php

namespace App\Services\Launch;

use App\Models\DailyFinancialSnapshot;
use App\Models\SettlementBatch;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;

class LaunchTimelineService
{
    public function __construct(
        private readonly SchedulerHeartbeatService $schedulerHeartbeatService,
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function snapshot(): array
    {
        $scheduler = $this->schedulerHeartbeatService->snapshot();

        $lastFinancialClose = DailyFinancialSnapshot::query()
            ->whereNotNull('finalized_at')
            ->orderByDesc('finalized_at')
            ->value('finalized_at');

        $lastSettlement = SettlementBatch::query()
            ->whereNotNull('finalized_at')
            ->orderByDesc('finalized_at')
            ->value('finalized_at');

        return [
            'last_backup' => $this->nullableTimestamp(SystemSettingKeys::BACKUP_LAST_RUN_AT),
            'last_verify_backup' => $this->nullableTimestamp(SystemSettingKeys::BACKUP_LAST_VERIFIED_AT),
            'last_pricing_audit' => $this->nullableTimestamp(SystemSettingKeys::PRICING_AUDIT_LAST_RUN_AT),
            'last_preflight' => $this->nullableTimestamp(SystemSettingKeys::PREFLIGHT_LAST_RUN_AT),
            'last_financial_close' => $lastFinancialClose?->toIso8601String(),
            'last_settlement' => $lastSettlement?->toIso8601String(),
            'last_scheduler_heartbeat' => $scheduler['last_run'] ?? $scheduler['last_run_at'] ?? null,
        ];
    }

    private function nullableTimestamp(string $key): ?string
    {
        $value = $this->settings->getString($key);

        return $value !== '' ? $value : null;
    }
}
