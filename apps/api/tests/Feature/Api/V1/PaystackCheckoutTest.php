<?php

namespace Tests\Feature\Api\V1;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class PaystackCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedProductCatalog();

        config([
            'services.paystack.enabled' => false,
            'services.paystack.secret_key' => null,
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.callback_url' => 'http://localhost:3000/payment/callback',
        ]);
    }

    public function test_paystack_disabled_mode_still_works(): void
    {
        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('data.payment_status', 'payment integration coming next')
            ->assertJsonMissingPath('data.authorization_url');

        Http::assertNothingSent();
    }

    public function test_paystack_enabled_without_secret_returns_clear_error(): void
    {
        config(['services.paystack.enabled' => true]);

        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Paystack secret key is not configured.',
                'errors' => ['code' => 'PAYSTACK_NOT_CONFIGURED'],
            ]);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_paystack_initialization_stores_authorization_url_when_mocked(): void
    {
        config(['services.paystack.enabled' => true, 'services.paystack.secret_key' => 'sk_test_secret']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/transaction/initialize')) {
                $payload = $request->data();

                $this->assertSame(110000, $payload['amount']);
                $this->assertSame('NGN', $payload['currency']);
                $this->assertSame('08031234567', $payload['metadata']['customer_phone']);
                $this->assertSame($payload['reference'], $payload['metadata']['paylity_reference']);
                $this->assertMatchesRegularExpression('/^PYL-\d{8}-[A-Z0-9]{6}$/', $payload['reference']);

                return Http::response([
                    'status' => true,
                    'message' => 'Authorization URL created',
                    'data' => [
                        'authorization_url' => 'https://checkout.paystack.com/test-auth',
                        'access_code' => 'ACCESS123',
                        'reference' => $payload['reference'],
                    ],
                ]);
            }

            return Http::response(['status' => false], 500);
        });

        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'customer_email' => 'user@example.com',
            'product_amount' => 1000,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'payment_pending')
            ->assertJsonPath('data.payment_provider', 'paystack')
            ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.com/test-auth')
            ->assertJsonPath('data.access_code', 'ACCESS123');

        $this->assertDatabaseHas('transactions', [
            'status' => 'payment_pending',
            'payment_provider' => 'paystack',
            'payment_authorization_url' => 'https://checkout.paystack.com/test-auth',
            'payable_amount' => 1100,
        ]);
    }

    public function test_paystack_amount_uses_payable_amount_in_kobo(): void
    {
        config(['services.paystack.enabled' => true, 'services.paystack.secret_key' => 'sk_test_secret']);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test-auth',
                    'access_code' => 'ACCESS123',
                    'reference' => 'PYL-20260702-TEST01',
                ],
            ]),
        ]);

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 10_000,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
        ])->assertCreated();

        Http::assertSent(function ($request) {
            return $request->data()['amount'] === 1_010_000;
        });
    }

    public function test_verify_endpoint_returns_transaction_when_paystack_disabled(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260702-VERIFY',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => 'payment_pending',
            'verified_phone' => false,
        ]);

        $response = $this->getJson('/api/v1/payments/paystack/verify/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.reference', 'PYL-20260702-VERIFY')
            ->assertJsonPath('data.payment_status', 'Payment confirmation coming next.')
            ->assertJsonPath('data.fulfillment_status', 'not_started');
    }
}
