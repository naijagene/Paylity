<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_airtime_receipt_shows_masked_recipient_phone_and_network_product_name(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260705-AIR001',
            'product_type' => 'airtime',
            'customer_phone' => '',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'request_payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08012345678',
            ],
            'verified_phone' => false,
        ]);

        $response = $this->getJson('/api/v1/transactions/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.receipt.product_display_name', 'MTN Airtime')
            ->assertJsonPath('data.receipt.phone_display', '0801 XXX 5678')
            ->assertJsonPath('data.receipt.customer_phone_masked', '0801 XXX 5678');
    }

    public function test_data_receipt_shows_network_and_plan_display_name(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260705-DATA01',
            'product_type' => 'data',
            'customer_phone' => '08031234567',
            'product_amount' => 1500,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1600,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'request_payload' => [
                'network' => 'MTN',
                'plan_name' => '1.5GB - 30 Days',
                'display_name' => '1.5GB - 30 Days',
                'variation_code' => 'mtn-1.5gb-30',
            ],
            'verified_phone' => false,
        ]);

        $response = $this->getJson('/api/v1/transactions/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.receipt.product_display_name', 'MTN 1.5GB - 30 Days');
    }

    public function test_electricity_receipt_shows_disco_and_meter_type(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260705-ELEC01',
            'product_type' => 'electricity',
            'customer_phone' => '',
            'product_amount' => 5000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 5100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'request_payload' => [
                'disco' => 'IKEDC',
                'meter_type' => 'prepaid',
                'billersCode' => '12345678901',
            ],
            'verified_phone' => false,
        ]);

        $response = $this->getJson('/api/v1/transactions/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.receipt.product_display_name', 'IKEDC Prepaid Electricity')
            ->assertJsonPath('data.receipt.phone_display', '1234 XXX 8901');
    }

    public function test_receipt_timestamp_falls_back_to_created_at(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260705-TIME01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'request_payload' => ['network' => 'MTN'],
            'verified_phone' => false,
            'created_at' => '2026-07-05 11:07:00',
        ]);

        $receipt = app(ReceiptService::class)->buildReceiptPayload($transaction->fresh());

        $this->assertNotNull($receipt['timestamp']);
        $this->assertNotNull($receipt['timestamp_display']);
        $this->assertStringContainsString('Jul 2026', $receipt['timestamp_display']);
        $this->assertStringContainsString('WAT', $receipt['timestamp_display']);
    }

    public function test_receipt_timestamp_prefers_paid_at(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260705-TIME02',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'request_payload' => ['network' => 'MTN'],
            'response_payload' => [
                'verify' => [
                    'data' => [
                        'paid_at' => '2026-07-05T11:07:00.000000Z',
                    ],
                ],
            ],
            'fulfilled_at' => '2026-07-05 12:30:00',
            'verified_phone' => false,
        ]);

        $receipt = app(ReceiptService::class)->buildReceiptPayload($transaction->fresh());

        $this->assertSame(
            '2026-07-05T11:07:00+00:00',
            $receipt['timestamp'],
        );
    }

    public function test_download_receipt_contains_same_displayed_fields(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260705-DL0001',
            'product_type' => 'airtime',
            'customer_phone' => '',
            'customer_email' => 'buyer@example.com',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'receipt_verification_token' => 'verifytoken1234567890abcdef1234',
            'request_payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08012345678',
            ],
            'verified_phone' => false,
        ]);

        $response = $this->get('/api/v1/transactions/'.$transaction->reference.'/receipt/download');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $html = $response->getContent();

        $this->assertStringContainsString('MTN Airtime', $html);
        $this->assertStringContainsString('0801 XXX 5678', $html);
        $this->assertStringContainsString('buyer@example.com', $html);
        $this->assertStringContainsString('Payment Successful', $html);
        $this->assertStringContainsString('Delivered', $html);
        $this->assertStringContainsString('WAT', $html);
        $this->assertStringContainsString('/verify/verifytoken1234567890abcdef1234', $html);
        $this->assertStringContainsString('receipt-card', $html);
        $this->assertStringContainsString('charges-box', $html);
        $this->assertStringContainsString('Payment Processing Fee', $html);
        $this->assertStringContainsString('badge-success', $html);
        $this->assertStringContainsString('PAYLITY', $html);
    }

    public function test_missing_phone_shows_dash(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260705-NOPH01',
            'product_type' => 'airtime',
            'customer_phone' => '',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'request_payload' => ['network' => 'MTN'],
            'verified_phone' => false,
        ]);

        $response = $this->getJson('/api/v1/transactions/'.$transaction->reference);

        $response
            ->assertOk()
            ->assertJsonPath('data.receipt.phone_display', '—')
            ->assertJsonPath('data.receipt.customer_phone_masked', '—');
    }
}
