<?php

namespace Tests\Integration;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Fulfillment\ElectricityMeterVerificationService;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Fulfillment\VTPassResponseMapper;
use App\Services\Fulfillment\VTPassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Live VTPass sandbox integration tests.
 *
 * Enable with FEATURE_VTPASS=true and VTPASS_SANDBOX_TESTS=true in .env.
 *
 * Partial certification (July 2026 sandbox run):
 * - Airtime purchase: CERTIFIED in sandbox when test_sandbox_airtime_purchase passes
 * - Electricity merchant verify: CERTIFIED in sandbox when test_sandbox_electricity_merchant_verify passes
 * - Data purchase: PENDING until VTPASS_TEST_DATA_VARIATION_CODE is set and test passes
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
            'reference' => 'PYL-SBOX-AIR-'.now()->format('His'),
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
        $variationCode = trim((string) config('services.vtpass.test_data_variation_code', ''));

        if ($variationCode === '') {
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

        $transaction = $this->createPaidTransaction([
            'reference' => 'PYL-SBOX-DATA-'.now()->format('His'),
            'product_type' => 'data',
            'customer_phone' => $phone,
            'product_amount' => 100,
            'payable_amount' => 200,
            'request_payload' => [
                'network' => $network,
                'recipient_phone' => $phone,
                'variation_code' => $variationCode,
            ],
        ]);

        $fulfilled = app(FulfillmentService::class)->fulfill($transaction->fresh());

        $this->assertSame(
            TransactionStatus::FULFILLED,
            $fulfilled->status,
            'Data sandbox purchase did not fulfill: '.($fulfilled->failure_reason ?? 'unknown'),
        );
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

        return 'Run test_sandbox_data_purchase — CERTIFIED when fulfilled';
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
        return Transaction::query()->create(array_merge([
            'reference' => 'PYL-SBOX-'.now()->format('Ymd-His'),
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
            ],
            'verified_phone' => false,
        ], $overrides));
    }
}
