<?php

namespace Tests\Feature;

use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\LaunchAuditEvent;
use App\Models\PaymentCertificationRun;
use App\Models\Transaction;
use App\Services\Finance\LedgerPostingService;
use App\Services\Launch\LaunchModeService;
use App\Services\Launch\PaystackModeInspector;
use App\Services\Launch\PaymentCertificationService;
use App\Services\Launch\PaymentLivePreflightService;
use App\Services\Platform\SystemSettingsService;
use App\Services\TransactionEventService;
use App\Support\Platform\SystemSettingKeys;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class Pay035LivePaymentCutoverTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSettingsSeeder::class);
        $this->seed(LedgerAccountSeeder::class);
        $this->seedProductCatalog();

        config([
            'services.operator.access_key' => self::OPERATOR_KEY,
            'services.paystack.enabled' => true,
            'services.paystack.public_key' => 'pk_test_abc1234567890',
            'services.paystack.secret_key' => 'sk_test_abc1234567890',
            'services.paystack.callback_url' => 'https://paylity.ng/payment/callback',
            'services.paystack.base_url' => 'https://api.paystack.co',
            'app.url' => 'https://api.paylity.ng',
            'app.frontend_url' => 'https://paylity.ng',
            'cors.allowed_origins_extra' => 'https://ops.paylity.ng',
        ]);
    }

    public function test_paystack_mode_detects_test_keys(): void
    {
        $report = app(PaystackModeInspector::class)->inspect();

        $this->assertSame(PaystackModeInspector::MODE_TEST, $report['detected_mode']);
        $this->assertSame(PaystackModeInspector::MODE_TEST, $report['public_key_mode']);
        $this->assertSame(PaystackModeInspector::MODE_TEST, $report['secret_key_mode']);
    }

    public function test_paystack_mode_detects_live_keys(): void
    {
        config([
            'services.paystack.public_key' => 'pk_live_abc1234567890',
            'services.paystack.secret_key' => 'sk_live_abc1234567890',
        ]);

        $report = app(PaystackModeInspector::class)->inspect();

        $this->assertSame(PaystackModeInspector::MODE_LIVE, $report['detected_mode']);
    }

    public function test_mixed_paystack_keys_are_blocked(): void
    {
        config([
            'services.paystack.public_key' => 'pk_live_abc1234567890',
            'services.paystack.secret_key' => 'sk_test_abc1234567890',
        ]);

        $report = app(PaystackModeInspector::class)->inspect();

        $this->assertSame(PaystackModeInspector::MODE_MIXED_INVALID, $report['detected_mode']);
        $this->assertSame(PaystackModeInspector::VERDICT_INVALID, $report['verdict']);
    }

    public function test_missing_paystack_keys_are_blocked(): void
    {
        config([
            'services.paystack.public_key' => '',
            'services.paystack.secret_key' => '',
        ]);

        $report = app(PaystackModeInspector::class)->inspect();

        $this->assertSame(PaystackModeInspector::MODE_MISSING, $report['detected_mode']);
        $this->assertSame(PaystackModeInspector::VERDICT_INVALID, $report['verdict']);
    }

    public function test_paystack_mode_command_never_exposes_secrets(): void
    {
        $this->artisan('paylity:paystack-mode', ['--json' => true])
            ->assertExitCode(0);

        $output = $this->artisan('paylity:paystack-mode')->run();
        $this->assertNotSame(1, $output);
    }

    public function test_strict_live_preflight_blocks_test_mode(): void
    {
        app(SystemSettingsService::class)->set(SystemSettingKeys::LAUNCH_MODE, LaunchModeService::MODE_SOFT_LAUNCH);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response(['status' => false], 404),
        ]);

        $report = app(PaymentLivePreflightService::class)->run(strict: true, persist: false);

        $this->assertSame(PaymentLivePreflightService::STATUS_BLOCKED, $report['verdict']);
    }

    public function test_live_preflight_verifies_callback_and_webhook_routes(): void
    {
        $report = app(PaymentLivePreflightService::class)->run(persist: false);
        $checks = collect($report['checks'])->keyBy('key');

        $this->assertSame('PASS', $checks['callback_route']['status']);
        $this->assertSame('PASS', $checks['webhook_route']['status']);
        $this->assertTrue(
            collect(Route::getRoutes())->contains(
                fn ($route) => in_array('POST', $route->methods(), true)
                    && str_contains($route->uri(), 'payments/paystack/webhook'),
            ),
        );
    }

    public function test_certification_run_can_be_created_and_linked(): void
    {
        $run = app(PaymentCertificationService::class)->createSession(
            productType: 'airtime',
            productAmountNaira: 100,
            phone: '08031234567',
            network: 'MTN',
            operator: 'ops-test',
            force: true,
        );

        $this->assertSame(PaymentCertificationRun::RESULT_INCOMPLETE, $run['result']);

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260720-CERT01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 100,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 303,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'payment_authorization_url' => 'https://checkout.paystack.com/test',
            'payment_reference' => 'PYL-20260720-CERT01',
            'request_payload' => ['network' => 'MTN', 'phone' => '08031234567'],
            'response_payload' => ['verify' => ['status' => 'success']],
            'receipt_verification_token' => 'receipt-token',
            'verified_phone' => false,
        ]);

        $linked = app(PaymentCertificationService::class)->linkReference(
            PaymentCertificationRun::query()->findOrFail($run['id']),
            $transaction->reference,
            'ops-test',
        );

        $this->assertSame($transaction->reference, $linked['reference']);
        $this->assertSame('PENDING', $linked['payment_status']);
    }

    public function test_certification_detects_missing_ledger_posting(): void
    {
        $run = PaymentCertificationRun::query()->create([
            'environment' => 'testing',
            'paystack_mode' => 'test',
            'provider_mode' => 'sandbox',
            'intended_product_type' => 'airtime',
            'intended_product_amount_kobo' => 10000,
            'expected_convenience_fee_kobo' => 10000,
            'expected_gateway_fee_kobo' => 10300,
            'expected_total_kobo' => 30300,
            'started_at' => now(),
            'result' => PaymentCertificationRun::RESULT_INCOMPLETE,
        ]);

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260720-CERT02',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 100,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 303,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'payment_authorization_url' => 'https://checkout.paystack.com/test',
            'payment_reference' => 'PYL-20260720-CERT02',
            'request_payload' => ['phone' => '08031234567'],
            'response_payload' => ['verify' => ['status' => 'success']],
            'verified_phone' => false,
        ]);

        $run->update(['reference' => $transaction->reference, 'transaction_id' => $transaction->id]);
        $payload = app(PaymentCertificationService::class)->refreshRun($run->fresh(), 'ops-test');

        $this->assertSame('PENDING', $payload['ledger_status']);
    }

    public function test_certification_detects_balanced_ledger(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260720-CERT03',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 100,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 303,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'payment_authorization_url' => 'https://checkout.paystack.com/test',
            'payment_reference' => 'PYL-20260720-CERT03',
            'request_payload' => ['phone' => '08031234567'],
            'response_payload' => ['verify' => ['status' => 'success']],
            'receipt_verification_token' => 'token',
            'verified_phone' => false,
        ]);

        app(LedgerPostingService::class)->postPaymentReceived($transaction);
        app(LedgerPostingService::class)->postFulfillmentRecognized($transaction->fresh());

        $run = PaymentCertificationRun::query()->create([
            'reference' => $transaction->reference,
            'transaction_id' => $transaction->id,
            'environment' => 'testing',
            'paystack_mode' => 'test',
            'provider_mode' => 'sandbox',
            'intended_product_type' => 'airtime',
            'intended_product_amount_kobo' => 10000,
            'expected_convenience_fee_kobo' => 10000,
            'expected_gateway_fee_kobo' => 10300,
            'expected_total_kobo' => 30300,
            'started_at' => now(),
            'result' => PaymentCertificationRun::RESULT_INCOMPLETE,
        ]);

        $payload = app(PaymentCertificationService::class)->refreshRun($run, 'ops-test');

        $this->assertSame('PASSED', $payload['ledger_status']);
    }

    public function test_soft_launch_daily_transaction_cap_enforced(): void
    {
        app(SystemSettingsService::class)->setMany([
            SystemSettingKeys::LAUNCH_MODE => LaunchModeService::MODE_SOFT_LAUNCH,
            SystemSettingKeys::LAUNCH_TRANSACTION_LIMIT_DAILY => 1,
        ]);

        Transaction::query()->create([
            'reference' => 'PYL-20260720-CAP01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 100,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 303,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
        ]);

        $this->expectException(\App\Exceptions\FraudCheckException::class);
        $this->expectExceptionMessage('launch transaction limit');

        try {
            app(LaunchModeService::class)->assertCheckoutAllowed('airtime', 303, 100);
        } catch (\App\Exceptions\FraudCheckException $exception) {
            $this->assertSame('DAILY_TRANSACTION_LIMIT_REACHED', $exception->errorCode);
            throw $exception;
        }
    }

    public function test_soft_launch_revenue_cap_enforced(): void
    {
        app(SystemSettingsService::class)->setMany([
            SystemSettingKeys::LAUNCH_MODE => LaunchModeService::MODE_SOFT_LAUNCH,
            SystemSettingKeys::LAUNCH_REVENUE_LIMIT_DAILY => 300,
        ]);

        Transaction::query()->create([
            'reference' => 'PYL-20260720-CAP02',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 100,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 303,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
        ]);

        try {
            app(LaunchModeService::class)->assertCheckoutAllowed('airtime', 303, 100);
            $this->fail('Expected revenue cap exception.');
        } catch (\App\Exceptions\FraudCheckException $exception) {
            $this->assertSame('DAILY_REVENUE_LIMIT_REACHED', $exception->errorCode);
        }
    }

    public function test_maintenance_blocks_new_checkouts(): void
    {
        app(SystemSettingsService::class)->set(SystemSettingKeys::LAUNCH_MODE, LaunchModeService::MODE_MAINTENANCE);

        try {
            app(LaunchModeService::class)->assertCheckoutAllowed('airtime', 303, 100);
            $this->fail('Expected maintenance exception.');
        } catch (\App\Exceptions\FraudCheckException $exception) {
            $this->assertSame('LAUNCH_MODE_MAINTENANCE', $exception->errorCode);
        }
    }

    public function test_maintenance_allows_webhook_processing(): void
    {
        app(SystemSettingsService::class)->set(SystemSettingKeys::LAUNCH_MODE, LaunchModeService::MODE_MAINTENANCE);

        $this->withIntegratedFeatureFlags([
            'FEATURE_PAYSTACK' => true,
        ]);

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260720-WH01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 100,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 303,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_PENDING,
            'payment_provider' => 'paystack',
            'verified_phone' => false,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 30300,
                    'reference' => $transaction->reference,
                    'currency' => 'NGN',
                ],
            ]),
        ]);

        $payload = json_encode([
            'id' => 9100,
            'event' => 'charge.success',
            'data' => ['reference' => $transaction->reference],
        ]);
        $signature = hash_hmac('sha512', $payload, (string) config('services.paystack.secret_key'));

        $this->call('POST', '/api/v1/payments/paystack/webhook', [], [], [], [
            'HTTP_X-Paystack-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_SUCCESS,
        ]);
    }

    public function test_rollback_records_audit_evidence(): void
    {
        app(SystemSettingsService::class)->set(SystemSettingKeys::LAUNCH_MODE, LaunchModeService::MODE_SOFT_LAUNCH);

        $this->artisan('paylity:payment-live-rollback', [
            '--maintenance' => true,
            '--confirm' => 'ENTER-MAINTENANCE',
        ])->assertSuccessful();

        $this->assertDatabaseHas('launch_audit_events', [
            'action' => 'live_payment_rollback',
        ]);
        $this->assertSame(
            LaunchModeService::MODE_MAINTENANCE,
            app(LaunchModeService::class)->mode(),
        );
    }

    public function test_ops_payment_certification_endpoints_require_operator_authentication(): void
    {
        $this->getJson('/api/v1/ops/go-live/payment-certification')
            ->assertUnauthorized();

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/go-live/payment-certification')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'paystack_mode',
                    'vtpass_mode',
                    'preflight_verdict',
                    'daily_usage',
                ],
            ]);

        $snapshot = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/go-live');

        $snapshot
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'payment_certification' => [
                        'paystack_mode',
                        'provider_mode',
                        'active_run',
                        'last_certified_transaction',
                        'last_certification_verdict',
                    ],
                ],
            ]);

        $encoded = json_encode($snapshot->json('data.payment_certification'));
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('sk_test_128e', $encoded);
        $this->assertStringNotContainsString('sk_live_', $encoded);
    }

    public function test_ops_payment_certification_create_requires_confirmation(): void
    {
        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/go-live/payment-certification', [
                'amount' => 100,
            ])
            ->assertStatus(422);

        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/go-live/payment-certification', [
                'amount' => 100,
                'confirm_live_certification' => true,
                'force' => true,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.result', PaymentCertificationRun::RESULT_INCOMPLETE);
    }

    public function test_evidence_export_contains_no_secrets(): void
    {
        $run = PaymentCertificationRun::query()->create([
            'environment' => 'testing',
            'paystack_mode' => 'test',
            'provider_mode' => 'sandbox',
            'intended_product_type' => 'airtime',
            'intended_product_amount_kobo' => 10000,
            'expected_convenience_fee_kobo' => 10000,
            'expected_gateway_fee_kobo' => 10300,
            'expected_total_kobo' => 30300,
            'started_at' => now(),
            'result' => PaymentCertificationRun::RESULT_INCOMPLETE,
        ]);

        $export = app(PaymentCertificationService::class)->export($run);
        $encoded = json_encode($export['payload']);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('sk_test_', $encoded);
        $this->assertStringNotContainsString('sk_live_', $encoded);
    }

    public function test_paystack_mode_json_output_contains_safe_prefixes_only(): void
    {
        $report = app(PaystackModeInspector::class)->inspect();

        $this->assertStringStartsWith('pk_test_', str_replace('…', '', (string) $report['public_key_prefix']).'x');
        $this->assertStringStartsWith('sk_test_', str_replace('…', '', (string) $report['secret_key_prefix']).'x');
        $this->assertStringNotContainsString('abc1234567890', json_encode($report));
    }

    public function test_certification_detects_amount_mismatch(): void
    {
        $run = PaymentCertificationRun::query()->create([
            'environment' => 'testing',
            'paystack_mode' => 'test',
            'provider_mode' => 'sandbox',
            'intended_product_type' => 'airtime',
            'intended_product_amount_kobo' => 10000,
            'expected_convenience_fee_kobo' => 10000,
            'expected_gateway_fee_kobo' => 10300,
            'expected_total_kobo' => 30300,
            'started_at' => now(),
            'result' => PaymentCertificationRun::RESULT_INCOMPLETE,
        ]);

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260720-CERT04',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 200,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 403,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'payment_authorization_url' => 'https://checkout.paystack.com/test',
            'payment_reference' => 'PYL-20260720-CERT04',
            'response_payload' => ['verify' => ['status' => 'success']],
            'verified_phone' => false,
        ]);

        $run->update(['reference' => $transaction->reference, 'transaction_id' => $transaction->id]);
        $payload = app(PaymentCertificationService::class)->refreshRun($run->fresh(), 'ops-test');
        $checks = $payload['evidence']['checks'] ?? [];

        $this->assertSame('WARN', $checks['checkout_amount']['status'] ?? null);
        $this->assertSame('WARN', $checks['paid_amount_match']['status'] ?? null);
    }

    public function test_certification_detects_webhook_and_fulfillment_events(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260720-CERT05',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 100,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 303,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'payment_authorization_url' => 'https://checkout.paystack.com/test',
            'payment_reference' => 'PYL-20260720-CERT05',
            'request_payload' => ['phone' => '08031234567'],
            'response_payload' => ['verify' => ['status' => 'success']],
            'receipt_verification_token' => 'token',
            'verified_phone' => false,
        ]);

        app(TransactionEventService::class)->record(
            $transaction,
            TransactionEventService::TYPE_WEBHOOK_RECEIVED,
            'Webhook received.',
        );

        $transaction->fulfillmentAttempts()->create([
            'provider' => 'vtpass',
            'status' => 'succeeded',
            'outcome' => 'succeeded',
            'request_id' => 'req-1',
            'attempt_number' => 1,
            'trigger_source' => 'payment_success',
            'attempted_at' => now(),
        ]);

        app(LedgerPostingService::class)->postPaymentReceived($transaction);
        app(LedgerPostingService::class)->postFulfillmentRecognized($transaction->fresh());

        $run = PaymentCertificationRun::query()->create([
            'reference' => $transaction->reference,
            'transaction_id' => $transaction->id,
            'environment' => 'testing',
            'paystack_mode' => 'test',
            'provider_mode' => 'sandbox',
            'intended_product_type' => 'airtime',
            'intended_product_amount_kobo' => 10000,
            'expected_convenience_fee_kobo' => 10000,
            'expected_gateway_fee_kobo' => 10300,
            'expected_total_kobo' => 30300,
            'intended_phone' => '08031234567',
            'started_at' => now(),
            'result' => PaymentCertificationRun::RESULT_INCOMPLETE,
        ]);

        $payload = app(PaymentCertificationService::class)->finalize($run, 'ops-test');

        $this->assertContains($payload['result'], [
            PaymentCertificationRun::RESULT_CERTIFIED,
            PaymentCertificationRun::RESULT_CERTIFIED_WITH_WARNINGS,
        ]);
        $this->assertSame('PASSED', $payload['fulfillment_status']);
    }
}
