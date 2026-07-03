<?php

namespace Tests\Integration;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Fulfillment\ElectricityMeterVerificationService;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Fulfillment\VTPassResponseMapper;
use App\Services\Fulfillment\VTPassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

        $this->assertContains($fulfilled->status, [
            TransactionStatus::FULFILLED,
            TransactionStatus::FAILED,
            TransactionStatus::FULFILLMENT_PENDING,
        ]);
    }

    public function test_sandbox_data_purchase(): void
    {
        $transaction = $this->createPaidTransaction([
            'reference' => 'PYL-SBOX-DATA-'.now()->format('His'),
            'product_type' => 'data',
            'product_amount' => 100,
            'payable_amount' => 200,
            'request_payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08011111111',
                'data_plan_id' => 'mtn-1gb-daily',
            ],
        ]);

        $fulfilled = app(FulfillmentService::class)->fulfill($transaction->fresh());

        $this->assertContains($fulfilled->status, [
            TransactionStatus::FULFILLED,
            TransactionStatus::FAILED,
            TransactionStatus::FULFILLMENT_PENDING,
        ]);
    }

    public function test_sandbox_electricity_merchant_verify(): void
    {
        $result = app(ElectricityMeterVerificationService::class)->verify(
            (string) config('services.vtpass.test_disco', 'IKEDC'),
            (string) config('services.vtpass.test_meter_number', '45053854956'),
            (string) config('services.vtpass.test_meter_type', 'prepaid'),
        );

        $this->assertTrue($result['available']);
        $this->assertNotSame('unavailable', $result['status']);
    }

    public function test_sandbox_invalid_meter_returns_failed_status(): void
    {
        $result = app(ElectricityMeterVerificationService::class)->verify(
            'IKEDC',
            '00000000000',
            'prepaid',
        );

        $this->assertTrue($result['available']);
        $this->assertFalse($result['verified']);
        $this->assertSame(VTPassResponseMapper::STATUS_FAILED, $result['status']);
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
