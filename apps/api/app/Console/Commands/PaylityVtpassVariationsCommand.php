<?php

namespace App\Console\Commands;

use App\Exceptions\VTPassConfigurationException;
use App\Exceptions\VTPassException;
use App\Services\Fulfillment\VTPassService;
use Illuminate\Console\Command;

class PaylityVtpassVariationsCommand extends Command
{
    protected $signature = 'paylity:vtpass-variations {serviceId : VTPass data service ID (e.g. mtn-data)}';

    protected $description = 'List VTPass sandbox service variations for data certification';

    public function __construct(
        private readonly VTPassService $vtpassService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $serviceId = trim((string) $this->argument('serviceId'));

        if ($serviceId === '') {
            $this->error('serviceId is required (e.g. mtn-data).');

            return self::FAILURE;
        }

        $this->info('PAYLITY NG — VTPass Service Variations');
        $this->line('Service ID: '.$serviceId);
        $this->newLine();

        if (! $this->vtpassService->isEnabled()) {
            $this->warn('FEATURE_VTPASS=false — enable it before querying variations.');

            return self::FAILURE;
        }

        try {
            $this->vtpassService->assertConfigured();
        } catch (VTPassConfigurationException) {
            $this->error('VTPass credentials are not configured.');

            return self::FAILURE;
        }

        try {
            $response = $this->vtpassService->getServiceVariations($serviceId);
        } catch (VTPassException $exception) {
            $this->error($this->sanitizeMessage($exception->getMessage()));
            $this->line($this->formatDiagnostics($exception->safeContext()));

            return self::FAILURE;
        }

        $variations = $this->extractVariations($response);

        if ($variations === []) {
            $message = (string) (
                data_get($response, 'response_description')
                ?? data_get($response, 'message')
                ?? 'No variations returned for this service ID.'
            );

            $this->warn($this->sanitizeMessage($message));
            $diagnostics = $this->vtpassService->lastRequestDiagnostics();

            if (is_array($diagnostics) && $diagnostics !== []) {
                $this->line($this->formatDiagnostics($diagnostics));
            }

            return self::FAILURE;
        }

        $rows = array_map(function (array $variation): array {
            return [
                (string) ($variation['variation_code'] ?? ''),
                (string) ($variation['name'] ?? ''),
                (string) ($variation['variation_amount'] ?? $variation['amount'] ?? ''),
                (string) ($variation['fixedPrice'] ?? $variation['fixed_price'] ?? ''),
            ];
        }, $variations);

        $this->table(
            ['variation_code', 'name', 'amount', 'fixedPrice'],
            $rows,
        );

        $this->newLine();
        $this->line('Copy a variation_code into VTPASS_TEST_DATA_VARIATION_CODE in apps/api/.env.');
        $this->line('Set VTPASS_TEST_DATA_SERVICE_ID='.$serviceId.' and VTPASS_TEST_DATA_PHONE as needed.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function extractVariations(array $response): array
    {
        $variations = data_get($response, 'content.variations');

        if (! is_array($variations)) {
            return [];
        }

        return array_values(array_filter(
            $variations,
            fn (mixed $variation) => is_array($variation),
        ));
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
}
