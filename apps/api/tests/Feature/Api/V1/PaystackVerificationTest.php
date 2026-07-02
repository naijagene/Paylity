<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.enabled' => true,
            'services.paystack.secret_key' => self::SECRET,
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.callback_url' => 'http://localhost:3000/payment/callback',
        ]);
    }

    public function test_verify_endpoint_marks_transaction_payment_success_when_mocked_paystack_status_success(): void
    {
        $transaction = $this->createPendingTransaction();

        $this->fakePaystackVerify([
            'status' => 'success',
            'amount' => 110000,
            'reference' => $transaction->reference,
            'gateway_response' => 'Successful',
            'paid_at' => '2026-07-02T22:00:00.000000Z',
            'channel' => 'card',
            'currency' => 'NGN',
        ]);

        $response = $this->getJson('/api/v1/payments/paystack/verify/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', TransactionStatus::PAYMENT_SUCCESS)
            ->assertJsonPath('data.payment_status', 'Payment successful.')
            ->assertJsonPath('data.fulfillment_status', 'not_started')
            ->assertJsonPath('data.verified_at', '2026-07-02T22:00:00.000000Z');

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'payment_reference' => $transaction->reference,
        ]);
    }

    public function test_verify_endpoint_blocks_amount_mismatch(): void
    {
        $transaction = $this->createPendingTransaction();

        $this->fakePaystackVerify([
            'status' => 'success',
            'amount' => 99999,
            'reference' => $transaction->reference,
            'currency' => 'NGN',
        ]);

        $response = $this->getJson('/api/v1/payments/paystack/verify/'.$transaction->reference);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code', 'AMOUNT_MISMATCH');

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_PENDING,
        ]);
    }

    public function test_verify_endpoint_blocks_reference_mismatch(): void
    {
        $transaction = $this->createPendingTransaction();

        $this->fakePaystackVerify([
            'status' => 'success',
            'amount' => 110000,
            'reference' => 'PYL-20260702-MISMATCH',
            'currency' => 'NGN',
        ]);

        $response = $this->getJson('/api/v1/payments/paystack/verify/'.$transaction->reference);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code', 'REFERENCE_MISMATCH');

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_PENDING,
        ]);
    }

    public function test_failed_payment_marks_payment_failed(): void
    {
        $transaction = $this->createPendingTransaction();

        $this->fakePaystackVerify([
            'status' => 'failed',
            'amount' => 110000,
            'reference' => $transaction->reference,
            'gateway_response' => 'Declined',
            'currency' => 'NGN',
        ]);

        $response = $this->getJson('/api/v1/payments/paystack/verify/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', TransactionStatus::PAYMENT_FAILED)
            ->assertJsonPath('data.failure_reason', 'Declined');

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_FAILED,
            'failure_reason' => 'Declined',
        ]);
    }

    public function test_pending_payment_keeps_payment_pending(): void
    {
        $transaction = $this->createPendingTransaction();

        $this->fakePaystackVerify([
            'status' => 'pending',
            'amount' => 110000,
            'reference' => $transaction->reference,
            'currency' => 'NGN',
        ]);

        $response = $this->getJson('/api/v1/payments/paystack/verify/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', TransactionStatus::PAYMENT_PENDING)
            ->assertJsonPath('data.payment_status', 'Payment pending.');

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_PENDING,
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => 'PYL-20260702-WEBHOOK'],
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/payments/paystack/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Paystack-Signature' => 'invalid-signature',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload,
        );

        $response
            ->assertStatus(401)
            ->assertJsonPath('errors.code', 'INVALID_SIGNATURE');
    }

    public function test_webhook_accepts_valid_charge_success_and_verifies_transaction(): void
    {
        $transaction = $this->createPendingTransaction();

        $this->fakePaystackVerify([
            'status' => 'success',
            'amount' => 110000,
            'reference' => $transaction->reference,
            'gateway_response' => 'Successful',
            'paid_at' => '2026-07-02T22:00:00.000000Z',
            'currency' => 'NGN',
        ]);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => $transaction->reference],
        ]);

        $signature = hash_hmac('sha512', $payload, self::SECRET);

        $response = $this->call(
            'POST',
            '/api/v1/payments/paystack/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Paystack-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload,
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_SUCCESS,
        ]);
    }

    public function test_paystack_disabled_mode_returns_placeholder_message(): void
    {
        config([
            'services.paystack.enabled' => false,
            'services.paystack.secret_key' => null,
        ]);

        $transaction = $this->createPendingTransaction();

        $response = $this->getJson('/api/v1/payments/paystack/verify/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.reference', $transaction->reference)
            ->assertJsonPath('data.status', TransactionStatus::PAYMENT_PENDING)
            ->assertJsonPath('data.payment_status', 'Payment confirmation coming next.')
            ->assertJsonPath('data.fulfillment_status', 'not_started');

        Http::assertNothingSent();
    }

    private function createPendingTransaction(): Transaction
    {
        return Transaction::query()->create([
            'reference' => 'PYL-20260702-VERIFY1',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_PENDING,
            'payment_provider' => 'paystack',
            'verified_phone' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fakePaystackVerify(array $data): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => $data,
            ]),
        ]);
    }
}
