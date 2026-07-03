<?php

namespace Tests\Integration;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Fulfillment\ElectricityMeterVerificationService;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Fulfillment\VTPassResponseMapper;
use App\Services\Fulfillment\VTPassService;
use App\Services\Fulfillment\VTPassRequestIdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Live VTPass sandbox integration tests.
 *
 * Enable with FEATURE_VTPASS=true and VTPASS_SANDBOX_TESTS=true in .env.
 *
 * Partial certification (July 2026 sandbox run):
 * - Airtime purchase: CERTIFIED in sandbox when test_sandbox_airtime_purchase passes
 * - Electricity merchant verify: CERTIFIED in sandbox when test_sandbox_electricity_merchant_verify passes
 * - Electricity purchase: CERTIFIED in sandbox when test_sandbox_electricity_purchase passes
 * - Data purchase: PENDING until test_sandbox_data_purchase passes (diagnostics printed on failure)
 * - Invalid meter rejection: SANDBOX-INCONCLUSIVE (sandbox may verify unexpected meters)
 */
class VTPassSandboxTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('FEATURE_VTPASS', false), FILTER_VALIDATE_BOOLEAN)
            || ! filter_var(env('VTPASS_SANDBOX_TESTS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped(
                'VTPass sandbox tests require FEATURE_VTPASS=true and VTPASS_SANDBOX_TESTS=true.',
            );
        }

        if (! app(VTPassService::class)->hasCredentials()) {
            $this->markTestSkipped('VTPass sandbox credentials are not configured.');
        }

        config(['services.operator.access_key' => self::OPERATOR_KEY]);
    }

    public function test_sandbox_certification_summary(): void
    {
        $lines = [
            'VTPass Sandbox Certification Summary',
            '----------------------------------',
            'Airtime: CERTIFIED in sandbox when test_sandbox_airtime_purchase passes with status fulfilled',
            'Electricity merchant verify: CERTIFIED in sandbox when test_sandbox_electricity_merchant_verify passes',
            'Electricity purchase: '.$this->electricityPurchaseCertificationStatus(),
            'Data: '.$this->dataCertificationStatus(),
            'Invalid meter behavior: SANDBOX-INCONCLUSIVE (see test_sandbox_invalid_meter_behavior_is_documented)',
            'Invalid network: CERTIFIED in sandbox when test_sandbox_invalid_network_returns_failed_status passes',
        ];

        fwrite(STDOUT, "\n".implode("\n", $lines)."\n\n");

        $this->assertTrue(true);
    }

    public function test_sandbox_airtime_purchase(): void
    {
        $transaction = $this->createPaidTransaction([
            'product_type' => 'airtime',
            'product_amount' => 100,
            'payable_amount' => 200,
            'request_payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08011111111',
            ],
        ]);

        $fulfilled = app(FulfillmentService::class)->fulfill($transaction->fresh());

        $this->assertSame(
            TransactionStatus::FULFILLED,
            $fulfilled->status,
            'Airtime sandbox purchase did not fulfill: '.($fulfilled->failure_reason ?? 'unknown'),
        );
    }

    public function test_sandbox_data_purchase(): void
    {
        $variationCodes = $this->dataVariationCodesToTry();

        if ($variationCodes === []) {
            $this->markTestSkipped(
                'Set VTPASS_TEST_DATA_VARIATION_CODE to a valid sandbox variation code.',
            );
        }

        $serviceId = trim((string) config('services.vtpass.test_data_service_id', ''));
        if ($serviceId === '') {
            $this->markTestSkipped(
                'Set VTPASS_TEST_DATA_SERVICE_ID (e.g. mtn-data) alongside VTPASS_TEST_DATA_VARIATION_CODE.',
            );
        }

        $phone = trim((string) config('services.vtpass.test_data_phone', '')) ?: '08011111111';
        $network = $this->networkForDataServiceId($serviceId);
        $attempts = [];

        foreach ($variationCodes as $label => $variationCode) {
            $attempts[$label] = $this->attemptDataPurchase($network, $phone, $variationCode, $serviceId);

            if ($attempts[$label]['fulfilled']) {
                $this->assertSame(TransactionStatus::FULFILLED, $attempts[$label]['transaction']->status);

                return;
            }
        }

        foreach ($attempts as $label => $attempt) {
            $this->printDataPurchaseFailureDiagnostics($attempt, $serviceId, $phone, $label);
        }

        $summary = collect($attempts)
            ->map(fn (array $attempt, string $label) => $label.': '.($attempt['failure_reason'] ?? 'unknown'))
            ->implode('; ');

        $this->fail('Data sandbox purchase did not fulfill. '.$summary);
    }

    public function test_sandbox_electricity_merchant_verify(): void
    {
        $result = app(ElectricityMeterVerificationService::class)->verify(
            (string) config('services.vtpass.test_disco', 'IKEDC'),
            (string) config('services.vtpass.test_meter_number', '45053854956'),
            (string) config('services.vtpass.test_meter_type', 'prepaid'),
        );

        $this->assertTrue($result['available']);
        $this->assertSame(
            VTPassResponseMapper::STATUS_SUCCESS,
            $result['status'],
            'Electricity merchant verify did not succeed: '.($result['message'] ?? 'unknown'),
        );
    }

    public function test_sandbox_electricity_purchase(): void
    {
        $meterNumber = trim((string) config('services.vtpass.test_electricity_meter_number', ''));

        if ($meterNumber === '') {
            $this->markTestSkipped(
                'Set VTPASS_TEST_ELECTRICITY_METER_NUMBER to a valid sandbox prepaid/postpaid meter.',
            );
        }

        $disco = trim((string) config('services.vtpass.test_electricity_disco', 'IKEDC'));
        $meterType = strtolower(trim((string) config('services.vtpass.test_electricity_meter_type', 'prepaid'))) ?: 'prepaid';
        $phone = trim((string) config('services.vtpass.test_electricity_phone', '')) ?: '08011111111';
        $amount = (int) config('services.vtpass.test_electricity_amount', 1000);

        if ($amount <= 0) {
            $this->markTestSkipped(
                'Set VTPASS_TEST_ELECTRICITY_AMOUNT to a positive sandbox purchase amount.',
            );
        }

        $verifyResult = app(ElectricityMeterVerificationService::class)->verify(
            $disco,
            $meterNumber,
            $meterType,
        );

        $this->assertTrue(
            $verifyResult['available'],
            'Electricity meter verification unavailable: '.($verifyResult['message'] ?? 'unknown'),
        );
        $this->assertSame(
            VTPassResponseMapper::STATUS_SUCCESS,
            $verifyResult['status'],
            'Electricity meter must verify before purchase: '.($verifyResult['message'] ?? 'unknown'),
        );
        $this->assertNotEmpty(
            $verifyResult['customer_name'],
            'Electricity meter verify must return a customer name before purchase. Check VTPASS_TEST_ELECTRICITY_METER_NUMBER.',
        );

        $transaction = $this->createPaidTransaction([
            'product_type' => 'electricity',
            'customer_phone' => $phone,
            'product_amount' => $amount,
            'payable_amount' => $amount + 100,
            'request_payload' => [
                'disco' => $disco,
                'meter_number' => $meterNumber,
                'meter_type' => $meterType,
                'customer_name' => $verifyResult['customer_name'] ?? 'Sandbox Customer',
            ],
        ]);

        $fulfilled = app(FulfillmentService::class)->fulfill($transaction->fresh());

        $this->assertSame(
            TransactionStatus::FULFILLED,
            $fulfilled->status,
            'Electricity sandbox purchase did not fulfill: '.($fulfilled->failure_reason ?? 'unknown'),
        );

        $fulfillment = (array) data_get($fulfilled->response_payload, 'fulfillment', []);

        $this->assertNotEmpty($fulfillment, 'Expected VTPass fulfillment response to be stored on the transaction.');
        $this->assertTrue(
            data_get($fulfillment, 'code') === '000'
            || data_get($fulfillment, 'response_description') === '000'
            || strtolower((string) data_get($fulfillment, 'response_description', '')) === 'transaction successful',
            'Expected a successful VTPass electricity purchase response.',
        );
        $this->assertNotEmpty(
            data_get($fulfillment, 'requestId')
            ?? data_get($fulfillment, 'content')
            ?? data_get($fulfillment, 'response_description'),
            'Expected meaningful VTPass purchase response metadata.',
        );

        $deliveryFieldSummary = $this->summarizeElectricityDeliveryFields($fulfillment);

        if ($deliveryFieldSummary !== '') {
            fwrite(
                STDOUT,
                "\nElectricity purchase delivery fields observed: {$deliveryFieldSummary}\n",
            );
        }
    }

    public function test_sandbox_invalid_meter_behavior_is_documented(): void
    {
        $result = app(ElectricityMeterVerificationService::class)->verify(
            'IKEDC',
            '00000000000',
            'prepaid',
        );

        $this->assertTrue($result['available']);

        if ($result['verified'] || $result['status'] === VTPassResponseMapper::STATUS_SUCCESS) {
            $this->markTestSkipped(
                'SANDBOX-INCONCLUSIVE: sandbox returned verified/success for meter 00000000000. Do not assume random meters fail in sandbox.',
            );
        }

        if ($result['status'] === VTPassResponseMapper::STATUS_FAILED) {
            $this->assertFalse($result['verified']);

            return;
        }

        $this->markTestSkipped(
            'SANDBOX-INCONCLUSIVE: sandbox returned status '.($result['status'] ?? 'unknown').' for meter 00000000000.',
        );
    }

    public function test_empty_meter_is_rejected_before_vtpass_api_call(): void
    {
        Http::fake();

        $result = app(ElectricityMeterVerificationService::class)->verify(
            'IKEDC',
            '',
            'prepaid',
        );

        $this->assertFalse($result['verified']);
        $this->assertSame(VTPassResponseMapper::STATUS_FAILED, $result['status']);
        $this->assertStringContainsString('Meter number is required', $result['message']);

        Http::assertNothingSent();
    }

    public function test_sandbox_invalid_network_returns_failed_status(): void
    {
        $result = app(ElectricityMeterVerificationService::class)->verify(
            'INVALID-DISCO',
            '45053854956',
            'prepaid',
        );

        $this->assertFalse($result['verified']);
        $this->assertSame(VTPassResponseMapper::STATUS_FAILED, $result['status']);
    }

    private function dataCertificationStatus(): string
    {
        $variationCode = trim((string) config('services.vtpass.test_data_variation_code', ''));

        if ($variationCode === '') {
            return 'PENDING valid variation code (set VTPASS_TEST_DATA_VARIATION_CODE)';
        }

        return 'PENDING until test_sandbox_data_purchase passes (diagnostics printed on failure)';
    }

    /**
     * @return array<string, string>
     */
    private function dataVariationCodesToTry(): array
    {
        $primary = trim((string) config('services.vtpass.test_data_variation_code', ''));
        $alternate = trim((string) config('services.vtpass.test_data_variation_code_alt', ''));

        $codes = [];

        if ($primary !== '') {
            $codes['primary'] = $primary;
        }

        if ($alternate !== '' && $alternate !== $primary) {
            $codes['alternate'] = $alternate;
        }

        return $codes;
    }

    /**
     * @return array{
     *     fulfilled: bool,
     *     variation_code: string,
     *     transaction: Transaction,
     *     failure_reason: string|null
     * }
     */
    private function attemptDataPurchase(
        string $network,
        string $phone,
        string $variationCode,
        string $serviceId,
    ): array {
        $transaction = $this->createPaidTransaction([
            'product_type' => 'data',
            'customer_phone' => $phone,
            'product_amount' => 100,
            'payable_amount' => 200,
            'request_payload' => [
                'network' => $network,
                'recipient_phone' => $phone,
                'variation_code' => $variationCode,
                'service_id' => $serviceId,
            ],
        ]);

        try {
            $fulfilled = app(FulfillmentService::class)->fulfill($transaction->fresh());

            return [
                'fulfilled' => true,
                'variation_code' => $variationCode,
                'transaction' => $fulfilled,
                'failure_reason' => null,
            ];
        } catch (\App\Exceptions\FulfillmentException $exception) {
            $failed = $transaction->fresh();

            return [
                'fulfilled' => false,
                'variation_code' => $variationCode,
                'transaction' => $failed,
                'failure_reason' => $failed->failure_reason ?? $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array{
     *     fulfilled: bool,
     *     variation_code: string,
     *     transaction: Transaction,
     *     failure_reason: string|null
     * }  $attempt
     */
    private function printDataPurchaseFailureDiagnostics(
        array $attempt,
        string $serviceId,
        string $phone,
        string $label,
    ): void {
        $transaction = $attempt['transaction'];
        $fulfillment = (array) data_get($transaction->response_payload, 'fulfillment', []);
        $requestPayload = (array) $transaction->request_payload;
        $contentErrors = $this->extractContentErrors($fulfillment);

        $lines = [
            'Data sandbox purchase diagnostics ['.$label.']',
            'vtpass_code='.(data_get($fulfillment, 'code') ?? 'n/a'),
            'response_description='.$this->sanitizeForStdout((string) (data_get($fulfillment, 'response_description') ?? 'n/a')),
            'failure_reason='.$this->sanitizeForStdout((string) ($attempt['failure_reason'] ?? 'n/a')),
            'serviceID='.$serviceId,
            'variation_code='.$attempt['variation_code'],
            'phone='.$phone,
            'request_id='.($requestPayload['request_id'] ?? 'n/a'),
        ];

        if ($contentErrors !== '') {
            $lines[] = 'content_errors='.$this->sanitizeForStdout($contentErrors);
        }

        $sanitizedPayload = $this->sanitizeArrayForStdout((array) $transaction->response_payload);
        $lines[] = 'response_payload='.json_encode($sanitizedPayload, JSON_UNESCAPED_SLASHES);

        fwrite(STDOUT, "\n".implode("\n", $lines)."\n\n");
    }

    /**
     * @param  array<string, mixed>  $fulfillment
     */
    private function extractContentErrors(array $fulfillment): string
    {
        $parts = [];

        foreach ([
            data_get($fulfillment, 'content.error'),
            data_get($fulfillment, 'content.errors'),
            data_get($fulfillment, 'content.transactions.error'),
            data_get($fulfillment, 'content.transactions.response_description'),
        ] as $value) {
            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            } elseif (is_array($value)) {
                $flattened = collect($value)
                    ->flatten()
                    ->filter(fn (mixed $item) => is_scalar($item) && trim((string) $item) !== '')
                    ->map(fn (mixed $item) => trim((string) $item))
                    ->values()
                    ->all();

                $parts = array_merge($parts, $flattened);
            }
        }

        return implode('; ', array_unique($parts));
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function sanitizeArrayForStdout(array $value): array
    {
        $sanitized = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $sanitized[$key] = $this->sanitizeArrayForStdout($item);

                continue;
            }

            $sanitized[$key] = is_string($item)
                ? $this->sanitizeForStdout($item)
                : $item;
        }

        return $sanitized;
    }

    private function sanitizeForStdout(string $value): string
    {
        $sanitized = $value;

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

    private function electricityPurchaseCertificationStatus(): string
    {
        $meterNumber = trim((string) config('services.vtpass.test_electricity_meter_number', ''));

        if ($meterNumber === '') {
            return 'PENDING valid test meter (set VTPASS_TEST_ELECTRICITY_METER_NUMBER)';
        }

        return 'CERTIFIED in sandbox when test_sandbox_electricity_purchase passes';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function summarizeElectricityDeliveryFields(array $response): string
    {
        $matches = [];

        $this->collectElectricityDeliveryFieldPaths($response, '', $matches);

        return implode(', ', array_unique($matches));
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $matches
     */
    private function collectElectricityDeliveryFieldPaths(mixed $value, string $prefix, array &$matches): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nested) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $normalizedKey = strtolower((string) $key);

            if (preg_match('/token|unit|recharge|pin|energy|kwh|tariff/', $normalizedKey) === 1) {
                $matches[] = $path;
            }

            if (is_array($nested)) {
                $this->collectElectricityDeliveryFieldPaths($nested, $path, $matches);
            }
        }
    }

    private function networkForDataServiceId(string $serviceId): string
    {
        return match (strtolower($serviceId)) {
            'mtn-data' => 'MTN',
            'airtel-data' => 'Airtel',
            'glo-data' => 'Glo',
            'etisalat-data' => '9mobile',
            default => throw new \InvalidArgumentException(
                'Unsupported VTPASS_TEST_DATA_SERVICE_ID: '.$serviceId,
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPaidTransaction(array $overrides = []): Transaction
    {
        $uniqueSuffix = now()->format('YmdHisv').'-'.Str::lower(Str::random(6));
        $requestId = VTPassRequestIdGenerator::generate();

        $defaults = [
            'reference' => 'PYL-SBOX-'.$uniqueSuffix,
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'request_payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
                'request_id' => $requestId,
            ],
            'verified_phone' => false,
        ];

        $merged = array_merge($defaults, $overrides);

        if (isset($overrides['request_payload'])) {
            $merged['request_payload'] = array_merge(
                $defaults['request_payload'],
                $overrides['request_payload'],
            );

            if (trim((string) ($merged['request_payload']['request_id'] ?? '')) === '') {
                $merged['request_payload']['request_id'] = VTPassRequestIdGenerator::generate();
            }
        }

        return Transaction::query()->create($merged);
    }
}
