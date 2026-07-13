<?php

namespace Tests\Feature\Console;

use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\LedgerTransaction;
use App\Models\Transaction;
use App\Services\Finance\LedgerBackfillService;
use App\Services\Finance\LedgerProductionValidationService;
use App\Services\Finance\LedgerPostingService;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Pay032dLedgerProductionValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_validation_report_identifies_status_mismatch_with_payment_evidence(): void
    {
        Transaction::query()->create([
            'reference' => 'PYL-20260713-PENDING01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_PENDING,
            'payment_reference' => 'PSK-PENDING-001',
            'response_payload' => ['verify' => ['status' => 'success']],
            'verified_phone' => false,
        ]);

        $report = app(LedgerProductionValidationService::class)->report();

        $this->assertSame(1, $report['step_2_payment_evidence_counts']['payment_reference_not_null']);
        $this->assertSame(1, $report['step_2_payment_evidence_counts']['verify_status_success']);
        $this->assertSame(0, $report['step_2_payment_evidence_counts']['webhook_data_status_success']);
        $this->assertCount(0, $report['step_3_fulfilled_transactions']);
        $this->assertSame(LedgerBackfillService::ROOT_CAUSE_STATUS_MISMATCH, $report['step_6_root_cause']['root_cause']);
        $this->assertSame(0, $report['ledger_posting_count']);
    }

    public function test_validation_report_lists_fulfilled_rows_missing_ledger_postings(): void
    {
        Transaction::query()->create([
            'reference' => 'PYL-20260713-FULFILLED01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234568',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'payment_reference' => 'PSK-FULFILLED-001',
            'fulfilled_at' => now()->subDay(),
            'verified_phone' => false,
        ]);

        $report = app(LedgerProductionValidationService::class)->report();

        $this->assertCount(1, $report['step_3_fulfilled_transactions']);
        $this->assertSame('PYL-20260713-FULFILLED01', $report['step_3_fulfilled_transactions'][0]['reference']);
        $this->assertFalse($report['step_3_fulfilled_transactions'][0]['ledger_payment_posting_exists']);
        $this->assertFalse($report['step_3_fulfilled_transactions'][0]['ledger_fulfillment_posting_exists']);
        $this->assertSame([], $report['step_5_eligible_exclusions']);
        $this->assertStringContainsString('where', $report['step_4_candidate_query']['sql']);
        $this->assertFalse($report['step_4_candidate_query']['model_inspection']['global_scopes_enabled']);
        $this->assertFalse($report['step_4_candidate_query']['model_inspection']['soft_deletes_enabled']);
    }

    public function test_validation_report_explains_already_posted_exclusions(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260713-POSTED01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234569',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'payment_reference' => 'PSK-POSTED-001',
            'fulfilled_at' => now()->subDay(),
            'verified_phone' => false,
        ]);

        app(LedgerPostingService::class)->postPaymentReceived($transaction);
        app(LedgerPostingService::class)->postFulfillmentRecognized($transaction->fresh());

        $report = app(LedgerProductionValidationService::class)->report();

        $this->assertGreaterThanOrEqual(2, $report['ledger_posting_count']);
        $this->assertCount(1, $report['step_5_eligible_exclusions']);
        $this->assertSame('PYL-20260713-POSTED01', $report['step_5_eligible_exclusions'][0]['reference']);
        $this->assertStringContainsString('has_payment_received_posting', $report['step_5_eligible_exclusions'][0]['condition']);
        $this->assertSame(LedgerBackfillService::ROOT_CAUSE_ALREADY_POSTED, $report['step_6_root_cause']['root_cause']);
    }

    public function test_validate_command_prints_step_outputs(): void
    {
        Transaction::query()->create([
            'reference' => 'PYL-20260713-CMD01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234570',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'payment_reference' => 'PSK-CMD-001',
            'fulfilled_at' => now()->subDay(),
            'verified_phone' => false,
        ]);

        $this->artisan('paylity:ledger-validate')
            ->expectsOutputToContain('STEP 1')
            ->expectsOutputToContain('STEP 2')
            ->expectsOutputToContain('STEP 3')
            ->expectsOutputToContain('STEP 4')
            ->expectsOutputToContain('STEP 5')
            ->expectsOutputToContain('STEP 6')
            ->expectsOutputToContain('PYL-20260713-CMD01')
            ->assertSuccessful();
    }

    public function test_ops_finance_validate_endpoint_requires_operator_auth(): void
    {
        $this->getJson('/api/v1/ops/finance/validate')
            ->assertUnauthorized();
    }

    public function test_ops_finance_validate_endpoint_returns_report_for_operator(): void
    {
        config(['services.operator.access_key' => 'test-operator-key']);

        Transaction::query()->create([
            'reference' => 'PYL-20260713-OPS01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234571',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'payment_reference' => 'PSK-OPS-001',
            'verified_phone' => false,
        ]);

        $this->withHeader('X-Operator-Key', 'test-operator-key')
            ->getJson('/api/v1/ops/finance/validate')
            ->assertOk()
            ->assertJsonPath('data.step_2_payment_evidence_counts.payment_reference_not_null', 1)
            ->assertJsonPath('data.step_6_root_cause.eligible_in_database', 1);
    }
}
