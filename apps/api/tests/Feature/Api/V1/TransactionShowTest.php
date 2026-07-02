<?php

namespace Tests\Feature\Api\V1;

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
                    'product_amount' => 1000,
                    'convenience_fee' => 100,
                    'gateway_fee' => 0,
                    'payable_amount' => 1100,
                    'currency' => 'NGN',
                    'status' => 'created',
                ],
            ]);
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
