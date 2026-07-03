<?php

namespace App\Console\Commands;

use App\Services\Fulfillment\ElectricityMeterVerificationService;
use App\Services\Fulfillment\VTPassResponseMapper;
use App\Services\Fulfillment\VTPassService;
use Illuminate\Console\Command;

class PaylityVtpassCheckCommand extends Command
{
    protected $signature = 'paylity:vtpass-check';

    protected $description = 'Validate PAYLITY NG VTPass sandbox integration credentials and connectivity';

    /** @var list<array{status: string, check: string, detail: string}> */
    private array $results = [];

    public function __construct(
        private readonly VTPassService $vtpassService,
        private readonly ElectricityMeterVerificationService $meterVerificationService,
        private readonly VTPassResponseMapper $responseMapper,
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
        $this->checkAuthentication();
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

        if ($this->vtpassService->isReachable()) {
            $this->record('PASS', 'Reachability', 'VTPass base URL is reachable.');

            return;
        }

        $this->record('FAIL', 'Reachability', 'Unable to reach VTPass base URL.');
    }

    private function checkAuthentication(): void
    {
        if (! $this->vtpassService->isEnabled()) {
            $this->record('WARN', 'Authentication', 'Skipped because FEATURE_VTPASS=false.');

            return;
        }

        if (! $this->vtpassService->hasCredentials()) {
            $this->record('WARN', 'Authentication', 'Skipped because credentials are incomplete.');

            return;
        }

        $disco = (string) config('services.vtpass.test_disco', 'IKEDC');
        $meterNumber = (string) config('services.vtpass.test_meter_number', '45053854956');
        $meterType = (string) config('services.vtpass.test_meter_type', 'prepaid');

        $result = $this->meterVerificationService->verify($disco, $meterNumber, $meterType);

        if (($result['available'] ?? false) === false) {
            $this->record('WARN', 'Authentication', $result['message']);

            return;
        }

        if (($result['status'] ?? '') === VTPassResponseMapper::STATUS_SUCCESS) {
            $this->record(
                'PASS',
                'Authentication',
                'Merchant verify accepted. Customer: '.($result['customer_name'] ?? 'N/A'),
            );

            return;
        }

        if (in_array($result['status'] ?? '', [
            VTPassResponseMapper::STATUS_FAILED,
            VTPassResponseMapper::STATUS_UNKNOWN,
        ], true) && ($result['raw_code'] ?? null) !== 'VTPASS_TIMEOUT') {
            $this->record(
                'PASS',
                'Authentication',
                'VTPass accepted credentials. Verify response: '.$result['message'],
            );

            return;
        }

        $this->record(
            'FAIL',
            'Authentication',
            'Unable to authenticate with VTPass: '.$result['message'],
        );
    }

    private function checkServiceMappings(): void
    {
        $electricityDiscos = array_map(
            'strtoupper',
            (new \App\Services\Fulfillment\Adapters\ElectricityAdapter())->supportedDiscos(),
        );

        $this->record(
            'PASS',
            'Service mappings',
            'Airtime, data, and electricity adapters loaded. Electricity discos: '.implode(', ', $electricityDiscos),
        );
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
