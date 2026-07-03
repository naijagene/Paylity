<?php

namespace App\Console\Commands;

use App\Services\Fulfillment\ElectricityMeterVerificationService;
use App\Services\Fulfillment\VTPassElectricityTestConfig;
use App\Services\Fulfillment\VTPassResponseMapper;
use App\Services\Fulfillment\VTPassService;
use Illuminate\Console\Command;
use Throwable;

class PaylityVtpassCheckCommand extends Command
{
    protected $signature = 'paylity:vtpass-check';

    protected $description = 'Validate PAYLITY NG VTPass sandbox integration credentials and connectivity';

    /** @var list<array{status: string, check: string, detail: string}> */
    private array $results = [];

    public function __construct(
        private readonly VTPassService $vtpassService,
        private readonly ElectricityMeterVerificationService $meterVerificationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('PAYLITY NG — VTPass Integration Check');
        $this->newLine();

        $this->checkFeatureFlag();
        $this->checkCredentials();
        $this->checkBaseUrl();
        $this->checkReachability();
        $this->checkMerchantVerify();
        $this->checkServiceMappings();

        $this->renderResults();

        $failCount = collect($this->results)->where('status', 'FAIL')->count();

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkFeatureFlag(): void
    {
        if (! $this->vtpassService->isEnabled()) {
            $this->record('WARN', 'FEATURE_VTPASS', 'FEATURE_VTPASS=false (integration disabled).');

            return;
        }

        $this->record('PASS', 'FEATURE_VTPASS', 'FEATURE_VTPASS=true');
    }

    private function checkCredentials(): void
    {
        $missing = [];

        if (empty(config('services.vtpass.username'))) {
            $missing[] = 'VTPASS_USERNAME';
        }

        if (empty(config('services.vtpass.password'))) {
            $missing[] = 'VTPASS_PASSWORD';
        }

        if (empty(config('services.vtpass.api_key'))) {
            $missing[] = 'VTPASS_API_KEY';
        }

        if ($missing !== []) {
            $this->record('FAIL', 'Credentials', 'Missing: '.implode(', ', $missing));

            return;
        }

        $this->record('PASS', 'Credentials', 'Username, password, and API key are configured.');
    }

    private function checkBaseUrl(): void
    {
        $baseUrl = (string) config('services.vtpass.base_url');

        if ($baseUrl === '') {
            $this->record('FAIL', 'Base URL', 'VTPASS_BASE_URL is not set.');

            return;
        }

        $this->record('PASS', 'Base URL', "VTPASS_BASE_URL={$baseUrl}");
    }

    private function checkReachability(): void
    {
        if (! $this->vtpassService->hasCredentials()) {
            $this->record('WARN', 'Reachability', 'Skipped because credentials are incomplete.');

            return;
        }

        try {
            if ($this->vtpassService->isReachable()) {
                $this->record('PASS', 'Reachability', 'VTPass base URL is reachable.');

                return;
            }

            $this->record('FAIL', 'Reachability', 'Unable to reach VTPass base URL.');
        } catch (Throwable $exception) {
            $this->record(
                'FAIL',
                'Reachability',
                'Reachability check failed: '.$this->sanitizeMessage($exception->getMessage()),
            );
        }
    }

    private function checkMerchantVerify(): void
    {
        if (! $this->vtpassService->isEnabled()) {
            $this->record('WARN', 'Merchant verify', 'Skipped because FEATURE_VTPASS=false.');

            return;
        }

        if (! $this->vtpassService->hasCredentials()) {
            $this->record('WARN', 'Merchant verify', 'Skipped because credentials are incomplete.');

            return;
        }

        $disco = VTPassElectricityTestConfig::disco();
        $meterNumber = VTPassElectricityTestConfig::meterNumber();
        $meterType = VTPassElectricityTestConfig::meterType();

        if (! VTPassElectricityTestConfig::isConfigured()) {
            $this->record(
                'WARN',
                'Merchant verify',
                'Skipped because '.VTPassElectricityTestConfig::missingConfigMessage(),
            );

            return;
        }

        try {
            $result = $this->meterVerificationService->verify($disco, $meterNumber, $meterType);

            if (($result['available'] ?? false) === false) {
                $this->record('WARN', 'Merchant verify', $result['message']);

                return;
            }

            if (($result['status'] ?? '') === VTPassResponseMapper::STATUS_SUCCESS) {
                $this->record(
                    'PASS',
                    'Merchant verify',
                    'Verify succeeded. Customer: '.($result['customer_name'] ?? 'N/A')
                        .'. '.$this->formatDiagnostics($result['diagnostics'] ?? []),
                );

                return;
            }

            $this->record(
                'FAIL',
                'Merchant verify',
                $this->formatVerifyFailure($result),
            );
        } catch (Throwable $exception) {
            $this->record(
                'FAIL',
                'Merchant verify',
                'Unexpected verify error: '.$this->sanitizeMessage($exception->getMessage()),
            );
        }
    }

    private function checkServiceMappings(): void
    {
        $electricityDiscos = array_map(
            'strtoupper',
            app(\App\Services\Fulfillment\ElectricityDiscoMapper::class)->supportedDiscos(),
        );

        $this->record(
            'PASS',
            'Service mappings',
            'Airtime, data, and electricity adapters loaded. Electricity discos: '.implode(', ', $electricityDiscos),
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatVerifyFailure(array $result): string
    {
        $message = (string) ($result['message'] ?? 'Merchant verify failed.');

        return $this->sanitizeMessage($message).'. '.$this->formatDiagnostics($result['diagnostics'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    private function formatDiagnostics(array $diagnostics): string
    {
        $parts = [];

        if (! empty($diagnostics['endpoint'])) {
            $parts[] = 'endpoint='.$diagnostics['endpoint'];
        }

        if (array_key_exists('http_status', $diagnostics) && $diagnostics['http_status'] !== null) {
            $parts[] = 'http_status='.$diagnostics['http_status'];
        }

        if (! empty($diagnostics['content_type'])) {
            $parts[] = 'content_type='.$this->sanitizeMessage((string) $diagnostics['content_type']);
        }

        if (! empty($diagnostics['vtpass_code'])) {
            $parts[] = 'vtpass_code='.$diagnostics['vtpass_code'];
        }

        if (! empty($diagnostics['vtpass_message'])) {
            $parts[] = 'vtpass_message='.$this->sanitizeMessage((string) $diagnostics['vtpass_message']);
        } elseif (! empty($diagnostics['safe_body_preview'])) {
            $parts[] = 'safe_body_preview='.$this->sanitizeMessage((string) $diagnostics['safe_body_preview']);
        }

        return $parts === [] ? 'No additional diagnostics available.' : implode('; ', $parts);
    }

    private function sanitizeMessage(string $message): string
    {
        $sanitized = $message;

        foreach ([
            (string) config('services.vtpass.password'),
            (string) config('services.vtpass.api_key'),
            (string) config('services.vtpass.secret_key'),
            (string) config('services.vtpass.username'),
        ] as $secret) {
            if ($secret !== '') {
                $sanitized = str_replace($secret, '[redacted]', $sanitized);
            }
        }

        return $sanitized;
    }

    private function record(string $status, string $check, string $detail): void
    {
        $this->results[] = [
            'status' => $status,
            'check' => $check,
            'detail' => $detail,
        ];
    }

    private function renderResults(): void
    {
        $rows = collect($this->results)->map(fn (array $result) => [
            $result['status'],
            $result['check'],
            $result['detail'],
        ])->all();

        $this->table(['Status', 'Check', 'Detail'], $rows);

        $pass = collect($this->results)->where('status', 'PASS')->count();
        $warn = collect($this->results)->where('status', 'WARN')->count();
        $fail = collect($this->results)->where('status', 'FAIL')->count();

        $this->newLine();
        $this->line("Summary: {$pass} PASS, {$warn} WARN, {$fail} FAIL");

        if ($fail > 0) {
            $this->error('VTPass check failed. Resolve FAIL items before sandbox certification.');
        } elseif ($warn > 0) {
            $this->warn('VTPass check passed with warnings.');
        } else {
            $this->info('VTPass check passed.');
        }
    }
}
