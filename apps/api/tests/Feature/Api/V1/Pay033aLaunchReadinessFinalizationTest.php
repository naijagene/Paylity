<?php

namespace Tests\Feature\Api\V1;

use App\Services\Launch\LaunchChecklistService;
use App\Services\Launch\LaunchModeService;
use App\Services\Launch\SchedulerHeartbeatService;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Pay033aLaunchReadinessFinalizationTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.operator.access_key' => self::OPERATOR_KEY]);
        $this->seed(PlatformSettingsSeeder::class);
        $this->seed(LedgerAccountSeeder::class);
    }

    public function test_scheduler_heartbeat_returns_next_expected_run(): void
    {
        $service = app(SchedulerHeartbeatService::class);
        $service->record();

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/go-live/heartbeat');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', SchedulerHeartbeatService::STATUS_HEALTHY)
            ->assertJsonPath('data.last_run', fn ($value) => $value !== null)
            ->assertJsonPath('data.next_expected_run', fn ($value) => $value !== null);
    }

    public function test_go_live_snapshot_includes_preflight_blockers_checklist_and_timeline(): void
    {
        app(SchedulerHeartbeatService::class)->record();

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/go-live');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'preflight' => ['status', 'summary', 'checks'],
                    'blockers',
                    'checklist' => ['items', 'progress_pct', 'ready_for_production'],
                    'timeline' => [
                        'last_backup',
                        'last_verify_backup',
                        'last_pricing_audit',
                        'last_preflight',
                        'last_financial_close',
                        'last_settlement',
                        'last_scheduler_heartbeat',
                    ],
                    'launch_status' => ['environment_badge', 'scheduler'],
                ],
            ]);
    }

    public function test_launch_preflight_returns_named_checks_with_severity(): void
    {
        app(SchedulerHeartbeatService::class)->record();

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/go-live/preflight');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'checks' => [
                        ['name', 'status', 'message', 'severity'],
                    ],
                ],
            ]);
    }

    public function test_checklist_persists_completion_state(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->patchJson('/api/v1/ops/go-live/checklist', [
            'items' => [
                'ssl_installed' => true,
                'domain_switched' => true,
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.completed_count', 2)
            ->assertJsonPath('data.progress_pct', 0);

        $snapshot = app(LaunchChecklistService::class)->snapshot();
        $this->assertSame(2, $snapshot['completed_count']);
    }

    public function test_checklist_progress_reaches_one_hundred_percent_when_complete(): void
    {
        $updates = [];
        foreach (LaunchChecklistService::ITEMS as $item) {
            $updates[$item['key']] = true;
        }

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->patchJson('/api/v1/ops/go-live/checklist', ['items' => $updates]);

        $response
            ->assertOk()
            ->assertJsonPath('data.progress_pct', 100)
            ->assertJsonPath('data.ready_for_production', true);
    }

    public function test_export_json_report_contains_required_sections(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/go-live/export/json');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'generated_at',
                    'environment',
                    'preflight',
                    'blockers',
                    'provider_modes',
                    'finance_summary',
                    'ledger_summary',
                    'wallet',
                    'build_version',
                ],
            ]);
    }

    public function test_export_pdf_report_returns_downloadable_html(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->get('/api/v1/ops/go-live/export/pdf');

        $response
            ->assertOk()
            ->assertHeader('content-disposition')
            ->assertSee('PAYLITY Launch Readiness Report', false);
    }

    public function test_production_mode_requires_confirmation(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/go-live/mode', ['mode' => 'live']);

        $response->assertStatus(422);

        $confirmed = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/go-live/mode', [
            'mode' => 'live',
            'confirm_production' => true,
        ]);

        $confirmed
            ->assertOk()
            ->assertJsonPath('data.mode', LaunchModeService::MODE_LIVE);
    }
}
