<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_can_be_fetched_by_reference(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260702-TEST01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'customer_email' => null,
            'customer_name' => null,
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => 'created',
            'request_payload' => ['network' => 'MTN'],
            'verified_phone' => false,
        ]);

        $response = $this->getJson('/api/v1/transactions/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Transaction retrieved successfully.',
                'data' => [
                    'reference' => 'PYL-20260702-TEST01',
                    'product_type' => 'airtime',
                    'customer_phone' => '08031234567',
                    'product_amount' => 1000,
                    'convenience_fee' => 100,
                    'gateway_fee' => 0,
                    'payable_amount' => 1100,
                    'currency' => 'NGN',
                    'status' => 'created',
                    'payment_provider' => null,
                    'payment_reference' => null,
                    'fulfillment_provider' => null,
                    'fulfillment_reference' => null,
                    'failure_reason' => null,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'reference',
                    'product_type',
                    'customer_phone',
                    'product_amount',
                    'convenience_fee',
                    'gateway_fee',
                    'payable_amount',
                    'currency',
                    'status',
                    'payment_provider',
                    'payment_reference',
                    'fulfillment_provider',
                    'fulfillment_reference',
                    'failure_reason',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_fulfilled_transaction_includes_fulfillment_details(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260702-FULFIL',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'payment_provider' => 'paystack',
            'payment_reference' => 'PYL-20260702-FULFIL',
            'fulfillment_provider' => 'vtpass',
            'fulfillment_reference' => 'vtpass-req-123',
            'verified_phone' => false,
        ]);

        $response = $this->getJson('/api/v1/transactions/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', TransactionStatus::FULFILLED)
            ->assertJsonPath('data.payment_provider', 'paystack')
            ->assertJsonPath('data.payment_reference', 'PYL-20260702-FULFIL')
            ->assertJsonPath('data.fulfillment_provider', 'vtpass')
            ->assertJsonPath('data.fulfillment_reference', 'vtpass-req-123');
    }

    public function test_failed_fulfillment_includes_failure_reason(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260702-FAILED1',
            'product_type' => 'data',
            'customer_phone' => '08031234567',
            'product_amount' => 350,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 450,
            'currency' => 'NGN',
            'status' => TransactionStatus::FAILED,
            'payment_provider' => 'paystack',
            'payment_reference' => 'PYL-20260702-FAILED1',
            'fulfillment_provider' => 'vtpass',
            'failure_reason' => 'TRANSACTION FAILED',
            'verified_phone' => false,
        ]);

        $response = $this->getJson('/api/v1/transactions/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', TransactionStatus::FAILED)
            ->assertJsonPath('data.failure_reason', 'TRANSACTION FAILED')
            ->assertJsonPath('data.fulfillment_provider', 'vtpass');
    }

    public function test_missing_transaction_returns_not_found(): void
    {
        $response = $this->getJson('/api/v1/transactions/PYL-20260702-MISSNG');

        $response
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Transaction not found.',
                'errors' => [
                    'code' => 'TRANSACTION_NOT_FOUND',
                ],
            ]);
    }
}
