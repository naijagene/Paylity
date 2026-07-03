<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VTPassFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vtpass.enabled' => false,
            'services.vtpass.auto_fulfill' => false,
            'services.vtpass.base_url' => 'https://sandbox.vtpass.com',
            'services.vtpass.username' => 'vtpass-user',
            'services.vtpass.password' => 'vtpass-pass',
            'services.vtpass.api_key' => 'vtpass-api-key',
            'services.paystack.enabled' => true,
            'services.paystack.secret_key' => 'sk_test_secret',
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.operator.access_key' => self::OPERATOR_KEY,
        ]);
    }

    public function test_ops_fulfillment_endpoint_blocked_when_feature_vtpass_is_false(): void
    {
        $transaction = $this->createPaidTransaction();

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/transactions/'.$transaction->reference.'/fulfill');

        $response
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'VTPass fulfillment is disabled.',
                'errors' => ['code' => 'VTPASS_DISABLED'],
            ]);
    }

    public function test_ops_fulfillment_rejects_unpaid_transaction(): void
    {
        config(['services.vtpass.enabled' => true]);

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260702-PENDING',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_PENDING,
            'request_payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
            'verified_phone' => false,
        ]);

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/transactions/'.$transaction->reference.'/fulfill');

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INVALID_TRANSACTION_STATUS');

        Http::assertNothingSent();
    }

    public function test_paid_airtime_transaction_maps_to_correct_vtpass_payload(): void
    {
        config(['services.vtpass.enabled' => true]);

        $transaction = $this->createPaidTransaction([
            'request_payload' => [
                'network' => '9mobile',
                'recipient_phone' => '08091112233',
            ],
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/api/pay')) {
                $payload = $request->data();

                $this->assertSame('etisalat', $payload['serviceID']);
                $this->assertSame(1000, $payload['amount']);
                $this->assertSame('08091112233', $payload['phone']);
                $this->assertStringStartsWith('PYL-20260702-FULFIL', $payload['request_id']);

                return Http::response([
                    'code' => '000',
                    'response_description' => 'TRANSACTION SUCCESSFUL',
                    'requestId' => 'vtpass-airtime-1',
                ]);
            }

            return Http::response(['code' => '999'], 500);
        });

        $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/transactions/'.$transaction->reference.'/fulfill')
            ->assertOk()
            ->assertJsonPath('data.status', TransactionStatus::FULFILLED)
            ->assertJsonPath('data.fulfillment_provider', 'vtpass')
            ->assertJsonPath('data.fulfillment_reference', 'vtpass-airtime-1');
    }

    public function test_paid_transaction_moves_to_fulfilled_when_mocked_vtpass_success(): void
    {
        config(['services.vtpass.enabled' => true]);

        $transaction = $this->createPaidTransaction();

        Http::fake([
            'https://sandbox.vtpass.com/api/pay' => Http::response([
                'code' => '000',
                'response_description' => 'TRANSACTION SUCCESSFUL',
                'requestId' => 'vtpass-success-1',
                'content' => [
                    'transactions' => [
                        'status' => 'delivered',
                        'transactionId' => 'vtpass-txn-1',
                    ],
                ],
            ]),
        ]);

        $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/transactions/'.$transaction->reference.'/fulfill')
            ->assertOk()
            ->assertJsonPath('data.status', TransactionStatus::FULFILLED);

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::FULFILLED,
            'fulfillment_provider' => 'vtpass',
            'fulfillment_reference' => 'vtpass-success-1',
        ]);
    }

    public function test_failed_vtpass_response_marks_transaction_failed(): void
    {
        config(['services.vtpass.enabled' => true]);

        $transaction = $this->createPaidTransaction();

        Http::fake([
            'https://sandbox.vtpass.com/api/pay' => Http::response([
                'code' => '016',
                'response_description' => 'TRANSACTION FAILED',
            ]),
        ]);

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/transactions/'.$transaction->reference.'/fulfill');

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VTPASS_FULFILLMENT_FAILED');

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::FAILED,
            'failure_reason' => 'TRANSACTION FAILED',
        ]);
    }

    public function test_auto_fulfillment_does_not_run_by_default(): void
    {
        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.auto_fulfill' => false,
        ]);

        $transaction = $this->createPaidTransaction();

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 110000,
                    'reference' => $transaction->reference,
                    'currency' => 'NGN',
                    'paid_at' => '2026-07-02T22:00:00.000000Z',
                ],
            ]),
        ]);

        $this->getJson('/api/v1/payments/paystack/verify/'.$transaction->reference)
            ->assertOk()
            ->assertJsonPath('data.status', TransactionStatus::PAYMENT_SUCCESS);

        Http::assertSentCount(1);

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_SUCCESS,
        ]);
    }

    public function test_auto_fulfillment_runs_only_when_both_flags_are_true(): void
    {
        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.auto_fulfill' => true,
        ]);

        $transaction = $this->createPaidTransaction([
            'status' => TransactionStatus::PAYMENT_PENDING,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 110000,
                    'reference' => $transaction->reference,
                    'currency' => 'NGN',
                ],
            ]),
            'https://sandbox.vtpass.com/api/pay' => Http::response([
                'code' => '000',
                'requestId' => 'vtpass-auto-1',
            ]),
        ]);

        $this->getJson('/api/v1/payments/paystack/verify/'.$transaction->reference)
            ->assertOk()
            ->assertJsonPath('data.status', TransactionStatus::FULFILLED)
            ->assertJsonPath('data.fulfillment_status', 'fulfilled');

        Http::assertSentCount(2);
    }

    public function test_electricity_adapter_builds_merchant_verify_payload(): void
    {
        $adapter = app(\App\Services\Fulfillment\Adapters\ElectricityAdapter::class);

        $payload = $adapter->buildVerifyPayload('IKEDC', '45053854956', 'prepaid');

        $this->assertSame('ikeja-electric', $payload['serviceID']);
        $this->assertSame('45053854956', $payload['billersCode']);
        $this->assertSame('prepaid', $payload['type']);
    }

    public function test_electricity_adapter_includes_meter_fields(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260702-ELEC01',
            'product_type' => 'electricity',
            'customer_phone' => '08031234567',
            'product_amount' => 5000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 5100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'request_payload' => [
                'disco' => 'IKEDC',
                'meter_type' => 'prepaid',
                'meter_number' => '12345678901',
                'customer_name' => 'John Doe',
            ],
            'verified_phone' => false,
        ]);

        $payload = app(\App\Services\Fulfillment\Adapters\ElectricityAdapter::class)->buildPayload($transaction);

        $this->assertSame('ikeja-electric', $payload['serviceID']);
        $this->assertSame('12345678901', $payload['billersCode']);
        $this->assertSame('12345678901', $payload['meter_number']);
        $this->assertSame('prepaid', $payload['meter_type']);
        $this->assertSame('prepaid', $payload['variation_code']);
        $this->assertSame(5000, $payload['amount']);
        $this->assertSame('08031234567', $payload['phone']);
    }

    public function test_data_adapter_includes_variation_code_from_data_plan_id(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260702-DATA01',
            'product_type' => 'data',
            'customer_phone' => '08031234567',
            'product_amount' => 350,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 450,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'request_payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
                'data_plan_id' => 'mtn-1gb-daily',
            ],
            'verified_phone' => false,
        ]);

        $payload = app(\App\Services\Fulfillment\Adapters\DataAdapter::class)->buildPayload($transaction);

        $this->assertSame('mtn-data', $payload['serviceID']);
        $this->assertSame('mtn-1gb-daily', $payload['variation_code']);
        $this->assertSame('08031234567', $payload['billersCode']);
    }

    public function test_airtime_adapter_maps_mtn_network_to_vtpass_service_id(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260702-AIR01',
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
        ]);

        $payload = app(\App\Services\Fulfillment\Adapters\AirtimeAdapter::class)->buildPayload($transaction);

        $this->assertSame('mtn', $payload['serviceID']);
        $this->assertSame(1000, $payload['amount']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPaidTransaction(array $overrides = []): Transaction
    {
        return Transaction::query()->create(array_merge([
            'reference' => 'PYL-20260702-FULFIL',
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
