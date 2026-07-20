<?php

namespace App\Services\Launch;

use App\Models\LaunchAuditEvent;
use Illuminate\Http\Request;

class LaunchAuditService
{
    public const ACTION_LIVE_PREFLIGHT = 'live_preflight_run';

    public const ACTION_CERTIFICATION_CREATED = 'certification_session_created';

    public const ACTION_CERTIFICATION_LINKED = 'certification_transaction_linked';

    public const ACTION_CERTIFICATION_FINALIZED = 'certification_finalized';

    public const ACTION_LAUNCH_MODE_CHANGED = 'launch_mode_changed';

    public const ACTION_MAINTENANCE_ENTERED = 'maintenance_mode_entered';

    public const ACTION_SOFT_LAUNCH_RESTORED = 'soft_launch_restored';

    public const ACTION_PRODUCTION_ENABLED = 'production_mode_enabled';

    public const ACTION_LIVE_ROLLBACK = 'live_payment_rollback';

    public const ACTION_CAPACITY_LIMITS_CHANGED = 'capacity_revenue_limits_changed';

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>|null  $new
     */
    public function record(
        string $action,
        ?array $previous = null,
        ?array $new = null,
        ?string $operator = null,
        ?string $reference = null,
        ?int $runId = null,
        ?string $reason = null,
        ?Request $request = null,
    ): LaunchAuditEvent {
        return LaunchAuditEvent::query()->create([
            'action' => $action,
            'operator' => $operator,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'previous_values' => $previous,
            'new_values' => $new,
            'reference' => $reference,
            'run_id' => $runId,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
