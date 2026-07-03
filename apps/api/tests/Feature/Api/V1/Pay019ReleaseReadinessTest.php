<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Pay019ReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_detail_includes_receipt_timeline_and_poll_metadata(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260703-RC0001',
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

        $response = $this->getJson('/api/v1/transactions/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.receipt.reference', $transaction->reference)
            ->assertJsonPath('data.is_terminal', false)
            ->assertJsonPath('data.poll_interval_seconds', 5)
            ->assertJsonStructure([
                'data' => [
                    'receipt' => [
                        'verification_url',
                        'verification_token',
                    ],
                    'timeline',
                ],
            ]);
    }

    public function test_receipt_verification_returns_public_payload(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260703-RC0002',
            'product_type' => 'data',
            'customer_phone' => '08031234567',
            'product_amount' => 500,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 600,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'receipt_verification_token' => 'abc123verifytoken456',
            'verified_phone' => false,
        ]);

        $response = $this->getJson('/api/v1/receipts/verify/abc123verifytoken456');

        $response
            ->assertOk()
            ->assertJsonPath('data.authentic', true)
            ->assertJsonPath('data.reference', $transaction->reference)
            ->assertJsonMissingPath('data.customer_phone');
    }

    public function test_customer_history_requires_phone_and_supports_filters(): void
    {
        Transaction::query()->create([
            'reference' => 'PYL-20260703-HIST01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
        ]);

        $this->getJson('/api/v1/transactions')
            ->assertStatus(422);

        $response = $this->getJson('/api/v1/transactions?phone=08031234567&status_group=delivered');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'PYL-20260703-HIST01');
    }

    public function test_health_endpoint_includes_database_check(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('data.checks.database', 'ok');
    }

    public function test_ops_monitoring_summary_includes_revenue_and_average_fulfillment(): void
    {
        config(['services.operator.access_key' => 'test-operator-key']);

        Transaction::query()->create([
            'reference' => 'PYL-20260703-MON001',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'fulfilled_at' => now(),
            'verified_phone' => false,
        ]);

        $response = $this->getJson('/api/v1/ops/monitoring', [
            'X-Operator-Key' => 'test-operator-key',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'revenue',
                    'transactions',
                    'failures',
                    'pending',
                    'average_fulfillment_seconds',
                ],
            ]);
    }

    public function test_payment_receipt_email_is_sent_on_payment_success(): void
    {
        Mail::fake();

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260703-MAIL01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'customer_email' => 'customer@example.com',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'verified_phone' => false,
        ]);

        app(\App\Services\Notifications\TransactionNotificationService::class)
            ->sendReceipt($transaction);

        Mail::assertSent(\App\Mail\TransactionReceiptMail::class);
    }
}
