<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpsConsoleTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.operator.access_key' => self::OPERATOR_KEY]);
    }

    public function test_ops_endpoints_reject_missing_key(): void
    {
        $response = $this->getJson('/api/v1/ops/transactions');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('errors.code', 'OPERATOR_ACCESS_DENIED');
    }

    public function test_ops_endpoints_reject_invalid_key(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => 'wrong-key',
        ])->getJson('/api/v1/ops/transactions');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('errors.code', 'OPERATOR_ACCESS_DENIED');
    }

    public function test_ops_list_works_with_valid_key(): void
    {
        Transaction::query()->create([
            'reference' => 'PYL-20260703-OPS001',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'verified_phone' => false,
        ]);

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/transactions?phone=0803');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.reference', 'PYL-20260703-OPS001')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_ops_detail_works_with_valid_key(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260703-OPS002',
            'product_type' => 'data',
            'customer_phone' => '08031234567',
            'product_amount' => 350,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 450,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'request_payload' => ['network' => 'MTN', 'data_plan_id' => 'mtn-1gb-daily'],
            'response_payload' => ['verify' => ['status' => 'success']],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'verified_phone' => false,
        ]);

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/transactions/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.reference', 'PYL-20260703-OPS002')
            ->assertJsonPath('data.request_payload.data_plan_id', 'mtn-1gb-daily')
            ->assertJsonPath('data.ip_address', '127.0.0.1');
    }

    public function test_ops_summary_works(): void
    {
        Transaction::query()->create([
            'reference' => 'PYL-20260703-OPS003',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'verified_phone' => false,
        ]);

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/summary');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_transactions_today',
                    'successful_payments_today',
                    'fulfilled_today',
                    'failed_today',
                    'pending_fulfillment',
                    'total_convenience_fees_today',
                ],
            ])
            ->assertJsonPath('data.pending_fulfillment', 1)
            ->assertJsonPath('data.total_convenience_fees_today', 100);
    }

    public function test_ops_fulfill_respects_feature_vtpass_false(): void
    {
        config(['services.vtpass.enabled' => false]);

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260703-OPS004',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'verified_phone' => false,
        ]);

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/transactions/'.$transaction->reference.'/fulfill');

        $response
            ->assertStatus(503)
            ->assertJsonPath('errors.code', 'VTPASS_DISABLED');
    }

    public function test_ops_fulfill_rejects_unpaid_transactions(): void
    {
        config(['services.vtpass.enabled' => true, 'services.vtpass.secret_key' => 'unused']);

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260703-OPS005',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_PENDING,
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
}
