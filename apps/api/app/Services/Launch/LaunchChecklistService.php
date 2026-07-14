<?php

namespace App\Services\Launch;

use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;

class LaunchChecklistService
{
    /** @var list<array{key: string, label: string}> */
    public const ITEMS = [
        ['key' => 'ssl_installed', 'label' => 'SSL installed'],
        ['key' => 'domain_switched', 'label' => 'Domain switched'],
        ['key' => 'live_paystack_configured', 'label' => 'Live Paystack configured'],
        ['key' => 'live_vtpass_configured', 'label' => 'Live VTPass configured'],
        ['key' => 'scheduler_verified', 'label' => 'Scheduler verified'],
        ['key' => 'callback_tested', 'label' => 'Callback tested'],
        ['key' => 'webhook_tested', 'label' => 'Webhook tested'],
        ['key' => 'wallet_funded', 'label' => 'Wallet funded'],
        ['key' => 'airtime_smoke_test', 'label' => 'Airtime smoke test'],
        ['key' => 'data_smoke_test', 'label' => 'Data smoke test'],
        ['key' => 'electricity_smoke_test', 'label' => 'Electricity smoke test'],
        ['key' => 'ledger_verified', 'label' => 'Ledger verified'],
        ['key' => 'finance_verified', 'label' => 'Finance verified'],
        ['key' => 'reconciliation_verified', 'label' => 'Reconciliation verified'],
    ];

    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, completed_count: int, total_count: int, progress_pct: int, ready_for_production: bool}
     */
    public function snapshot(): array
    {
        $completed = $this->completedKeys();
        $items = [];

        foreach (self::ITEMS as $item) {
            $isCompleted = in_array($item['key'], $completed, true);
            $items[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'completed' => $isCompleted,
                'completed_at' => $isCompleted ? ($this->completedAt($item['key']) ?: null) : null,
            ];
        }

        $total = count(self::ITEMS);
        $done = count($completed);
        $progressPct = $this->progressPercent($done, $total);

        return [
            'items' => $items,
            'completed_count' => $done,
            'total_count' => $total,
            'progress_pct' => $progressPct,
            'ready_for_production' => $done === $total,
        ];
    }

    /**
     * @param  array<string, bool>  $updates
     * @return array{items: list<array<string, mixed>>, completed_count: int, total_count: int, progress_pct: int, ready_for_production: bool}
     */
    public function update(array $updates): array
    {
        $state = $this->loadState();

        foreach ($updates as $key => $completed) {
            if (! $this->isValidKey((string) $key)) {
                continue;
            }

            if ($completed) {
                $state[(string) $key] = [
                    'completed' => true,
                    'completed_at' => now()->toIso8601String(),
                ];
            } else {
                unset($state[(string) $key]);
            }
        }

        $this->settings->set(SystemSettingKeys::LAUNCH_CHECKLIST, json_encode($state) ?: '{}');

        return $this->snapshot();
    }

    public function progressPercent(int $completed, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        $ratio = $completed / $total;

        return (int) (floor($ratio * 4) * 25);
    }

    /**
     * @return list<string>
     */
    private function completedKeys(): array
    {
        $state = $this->loadState();

        return collect($state)
            ->filter(fn (mixed $entry): bool => is_array($entry) && ($entry['completed'] ?? false) === true)
            ->keys()
            ->map(fn (mixed $key): string => (string) $key)
            ->values()
            ->all();
    }

    private function completedAt(string $key): ?string
    {
        $state = $this->loadState();
        $entry = $state[$key] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        $completedAt = $entry['completed_at'] ?? null;

        return is_string($completedAt) && $completedAt !== '' ? $completedAt : null;
    }

    /**
     * @return array<string, array{completed: bool, completed_at?: string}>
     */
    private function loadState(): array
    {
        $raw = $this->settings->get(SystemSettingKeys::LAUNCH_CHECKLIST, '{}');

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function isValidKey(string $key): bool
    {
        return collect(self::ITEMS)->contains(fn (array $item): bool => $item['key'] === $key);
    }
}
