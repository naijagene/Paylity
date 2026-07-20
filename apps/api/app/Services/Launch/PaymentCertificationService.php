<?php

namespace App\Services\Launch;

use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\PaymentCertificationRun;
use App\Models\Transaction;
use App\Services\FeeService;
use App\Services\Finance\FinancialAlertService;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Launch\SchedulerHeartbeatService;
use App\Services\Ops\OpsReliabilityService;
use App\Services\Platform\SystemSettingsService;
use App\Services\TransactionEventService;
use App\Support\Finance\Money;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PaymentCertificationService
{
    /**
     * @return array<string, mixed>
     */
    public static function emptySnapshot(
        ?string $environment = null,
        ?string $launchMode = null,
        ?string $preflightVerdict = null,
        ?string $lastBackupAt = null,
        ?string $schedulerHealth = null,
    ): array {
        return [
            'paystack_mode' => 'unknown',
            'provider_mode' => 'unknown',
            'vtpass_mode' => 'unknown',
            'environment' => $environment ?? (string) config('app.env', 'unknown'),
            'launch_mode' => $launchMode ?? 'unknown',
            'preflight_verdict' => $preflightVerdict ?? 'UNKNOWN',
            'active_run' => null,
            'last_certified_transaction' => null,
            'last_certification_verdict' => null,
            'last_certified' => null,
            'daily_transaction_usage' => [
                'transaction_count' => 0,
                'transaction_limit_daily' => 0,
                'transaction_utilization_pct' => null,
            ],
            'daily_revenue_usage' => [
                'gross_collection_naira' => 0,
                'revenue_limit_daily' => 0,
                'revenue_utilization_pct' => null,
            ],
            'daily_usage' => [
                'transaction_count' => 0,
                'gross_collection_naira' => 0,
                'transaction_limit_daily' => 0,
                'revenue_limit_daily' => 0,
                'transaction_utilization_pct' => null,
                'revenue_utilization_pct' => null,
            ],
            'last_backup_at' => $lastBackupAt,
            'scheduler_health' => $schedulerHealth ?? 'unknown',
        ];
    }
    public function __construct(
        private readonly PaystackModeInspector $paystackModeInspector,
        private readonly VtpassModeInspector $vtpassModeInspector,
        private readonly LaunchModeService $launchModeService,
        private readonly PaymentLivePreflightService $paymentLivePreflightService,
        private readonly FeeService $feeService,
        private readonly FinancialLedgerService $financialLedgerService,
        private readonly FinancialAlertService $financialAlertService,
        private readonly OpsReliabilityService $opsReliabilityService,
        private readonly TransactionEventService $transactionEventService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        if (! Schema::hasTable('payment_certification_runs')) {
            return self::emptySnapshot();
        }

        $dailyUsage = $this->launchModeService->dailyUsage();
        $scheduler = app(SchedulerHeartbeatService::class)->snapshot();
        $settings = app(SystemSettingsService::class);
        $vtpassMode = (string) ($this->vtpassModeInspector->inspect()['mode'] ?? 'unknown');

        $activeRun = $this->activeRun();
        $lastCertified = PaymentCertificationRun::query()
            ->whereIn('result', [
                PaymentCertificationRun::RESULT_CERTIFIED,
                PaymentCertificationRun::RESULT_CERTIFIED_WITH_WARNINGS,
            ])
            ->orderByDesc('completed_at')
            ->first();

        $lastCertifiedPayload = $lastCertified ? $this->serializeRun($lastCertified) : null;

        return [
            'paystack_mode' => (string) ($this->paystackModeInspector->inspect()['detected_mode'] ?? 'unknown'),
            'provider_mode' => $vtpassMode,
            'vtpass_mode' => $vtpassMode,
            'environment' => (string) config('app.env'),
            'launch_mode' => $this->launchModeService->mode(),
            'preflight_verdict' => $settings->getString(
                SystemSettingKeys::PAYMENT_LIVE_PREFLIGHT_LAST_STATUS,
                'UNKNOWN',
            ),
            'active_run' => $activeRun ? $this->serializeRun($activeRun) : null,
            'last_certified_transaction' => $lastCertifiedPayload,
            'last_certification_verdict' => $lastCertifiedPayload['result'] ?? null,
            'last_certified' => $lastCertifiedPayload,
            'daily_transaction_usage' => [
                'transaction_count' => (int) ($dailyUsage['transaction_count'] ?? 0),
                'transaction_limit_daily' => (int) ($dailyUsage['transaction_limit_daily'] ?? 0),
                'transaction_utilization_pct' => $dailyUsage['transaction_utilization_pct'] ?? null,
            ],
            'daily_revenue_usage' => [
                'gross_collection_naira' => (int) ($dailyUsage['gross_collection_naira'] ?? 0),
                'revenue_limit_daily' => (int) ($dailyUsage['revenue_limit_daily'] ?? 0),
                'revenue_utilization_pct' => $dailyUsage['revenue_utilization_pct'] ?? null,
            ],
            'daily_usage' => $dailyUsage,
            'last_backup_at' => $settings->getString(SystemSettingKeys::BACKUP_LAST_RUN_AT) ?: null,
            'scheduler_health' => (string) ($scheduler['status'] ?? 'unknown'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createSession(
        string $productType = 'airtime',
        int $productAmountNaira = 100,
        ?string $phone = null,
        ?string $network = null,
        ?string $operator = null,
        bool $force = false,
    ): array {
        if (! $force && $this->activeRun() !== null) {
            throw ValidationException::withMessages([
                'run' => ['An active certification run already exists. Finalize or override it first.'],
            ]);
        }

        $quote = $this->feeService->quote($productType, $productAmountNaira);
        $paystack = $this->paystackModeInspector->inspect();
        $vtpass = $this->vtpassModeInspector->inspect();

        $run = PaymentCertificationRun::query()->create([
            'environment' => (string) config('app.env'),
            'paystack_mode' => (string) ($paystack['detected_mode'] ?? 'unknown'),
            'provider_mode' => (string) ($vtpass['mode'] ?? 'unknown'),
            'intended_product_type' => $productType,
            'intended_product_amount_kobo' => Money::nairaToKobo($productAmountNaira),
            'expected_convenience_fee_kobo' => Money::nairaToKobo((int) $quote['convenience_fee']),
            'expected_gateway_fee_kobo' => Money::nairaToKobo((int) $quote['gateway_fee']),
            'expected_total_kobo' => Money::nairaToKobo((int) $quote['payable_amount']),
            'intended_phone' => $phone,
            'intended_network' => $network ? strtoupper($network) : null,
            'started_by' => $operator,
            'started_at' => now(),
            'result' => PaymentCertificationRun::RESULT_INCOMPLETE,
        ]);

        return $this->serializeRun($run->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function linkReference(PaymentCertificationRun $run, string $reference, ?string $operator = null): array
    {
        $transaction = Transaction::query()->where('reference', $reference)->first();

        if (! $transaction) {
            throw ValidationException::withMessages([
                'reference' => ['Transaction reference not found.'],
            ]);
        }

        $run->update([
            'reference' => $reference,
            'transaction_id' => $transaction->id,
        ]);

        return $this->refreshRun($run->fresh(), $operator);
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshRun(PaymentCertificationRun $run, ?string $operator = null): array
    {
        $evidence = $this->buildEvidence($run->fresh());
        $statuses = $this->deriveStatuses($evidence);

        $run->update([
            'payment_status' => $statuses['payment_status'],
            'fulfillment_status' => $statuses['fulfillment_status'],
            'ledger_status' => $statuses['ledger_status'],
            'reconciliation_status' => $statuses['reconciliation_status'],
            'settlement_expectation_status' => $statuses['settlement_expectation_status'],
            'receipt_status' => $statuses['receipt_status'],
            'evidence_json' => $evidence,
            'result' => $this->deriveResult($evidence, finalized: false),
        ]);

        return $this->serializeRun($run->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function finalize(PaymentCertificationRun $run, ?string $operator = null): array
    {
        $this->refreshRun($run, $operator);
        $run = $run->fresh();
        $evidence = is_array($run->evidence_json) ? $run->evidence_json : [];
        $result = $this->deriveResult($evidence, finalized: true);

        $run->update([
            'result' => $result,
            'completed_at' => now(),
        ]);

        return $this->serializeRun($run->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function export(PaymentCertificationRun $run): array
    {
        $payload = $this->serializeRun($run->fresh());
        $encoded = json_encode($payload);

        return [
            'filename' => 'paylity-live-payment-certification-'.$run->id.'.json',
            'content_type' => 'application/json',
            'payload' => $payload,
            'sha256' => hash('sha256', is_string($encoded) ? $encoded : ''),
        ];
    }

    public function activeRun(): ?PaymentCertificationRun
    {
        if (! Schema::hasTable('payment_certification_runs')) {
            return null;
        }

        return PaymentCertificationRun::query()
            ->where('result', PaymentCertificationRun::RESULT_INCOMPLETE)
            ->whereNull('completed_at')
            ->orderByDesc('id')
            ->first();
    }

    public function findRun(int $id): PaymentCertificationRun
    {
        return PaymentCertificationRun::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEvidence(PaymentCertificationRun $run): array
    {
        $checks = [];
        $transaction = $run->transaction_id
            ? Transaction::query()
                ->with(['events', 'fulfillmentAttempts', 'ledgerTransactions', 'financial'])
                ->find($run->transaction_id)
            : null;

        $checks['checkout_amount'] = $this->evidenceCheck(
            $transaction !== null && (int) $transaction->product_amount === Money::koboToNaira((int) $run->intended_product_amount_kobo),
            'Product amount matches intended certification amount.',
            $transaction ? 'Product amount mismatch.' : 'Transaction not linked yet.',
        );
        $checks['convenience_fee'] = $this->evidenceCheck(
            $transaction !== null && Money::nairaToKobo((int) $transaction->convenience_fee) === (int) $run->expected_convenience_fee_kobo,
            'Convenience fee matches expected value.',
            $transaction ? 'Convenience fee mismatch.' : 'Transaction not linked yet.',
        );
        $checks['gateway_fee'] = $this->evidenceCheck(
            $transaction !== null && Money::nairaToKobo((int) $transaction->gateway_fee) === (int) $run->expected_gateway_fee_kobo,
            'Gateway fee matches expected value.',
            $transaction ? 'Gateway fee mismatch.' : 'Transaction not linked yet.',
        );
        $checks['paystack_authorization_url'] = $this->evidenceCheck(
            $transaction !== null && ($transaction->payment_authorization_url ?? '') !== '',
            'Paystack authorization URL generated.',
            $transaction ? 'Authorization URL missing.' : 'Transaction not linked yet.',
        );
        $checks['paystack_reference_match'] = $this->evidenceCheck(
            $transaction !== null
                && (($transaction->payment_reference ?: $transaction->reference) === $transaction->reference),
            'Paystack reference matches PAYLITY reference.',
            $transaction ? 'Reference mismatch.' : 'Transaction not linked yet.',
        );
        $checks['callback_received'] = $this->evidenceCheck(
            $transaction !== null && (
                in_array($transaction->status, [
                    TransactionStatus::PAYMENT_SUCCESS,
                    TransactionStatus::FULFILLMENT_PENDING,
                    TransactionStatus::FULFILLED,
                ], true)
                || data_get($transaction->response_payload, 'verify') !== null
            ),
            'Payment verification progressed beyond pending checkout.',
            'Callback/verify progression not observed yet.',
        );
        $checks['webhook_received'] = $this->evidenceCheck(
            $transaction !== null && $transaction->events->contains(
                fn ($event) => $event->event_type === TransactionEventService::TYPE_WEBHOOK_RECEIVED,
            ),
            'Paystack webhook received.',
            'Webhook event not recorded yet.',
        );
        $checks['paystack_verification_success'] = $this->evidenceCheck(
            $transaction !== null && data_get($transaction->response_payload, 'verify.status') === 'success',
            'Paystack verification succeeded.',
            'Paystack verification not recorded as success.',
        );
        $checks['paid_amount_match'] = $this->evidenceCheck(
            $transaction !== null && Money::nairaToKobo((int) $transaction->payable_amount) === (int) $run->expected_total_kobo,
            'Paid amount equals expected total.',
            'Paid amount mismatch.',
        );
        $checks['currency_ngn'] = $this->evidenceCheck(
            $transaction !== null && strtoupper((string) $transaction->currency) === 'NGN',
            'Currency is NGN.',
            'Currency is not NGN.',
        );
        $checks['payment_status_advanced'] = $this->evidenceCheck(
            $transaction !== null && in_array($transaction->status, [
                TransactionStatus::PAYMENT_SUCCESS,
                TransactionStatus::FULFILLMENT_PENDING,
                TransactionStatus::FULFILLED,
            ], true),
            'Payment status advanced correctly.',
            'Payment status has not advanced.',
        );

        $attemptCount = $transaction?->fulfillmentAttempts?->count() ?? 0;
        $successAttempts = $transaction?->fulfillmentAttempts
            ?->where('status', 'succeeded')
            ->count() ?? 0;

        $checks['fulfillment_initiated_once'] = $this->evidenceCheck(
            $transaction !== null && $attemptCount === 1,
            'Fulfillment initiated exactly once.',
            "Fulfillment attempts recorded: {$attemptCount}.",
        );
        $checks['fulfillment_succeeded_once'] = $this->evidenceCheck(
            $transaction !== null && $successAttempts === 1 && $transaction->status === TransactionStatus::FULFILLED,
            'Fulfillment succeeded once.',
            'Fulfillment has not succeeded exactly once.',
        );
        $checks['no_duplicate_fulfillment'] = $this->evidenceCheck(
            $transaction !== null && $successAttempts <= 1,
            'No duplicate successful fulfillment detected.',
            'Duplicate successful fulfillment detected.',
        );

        $recipientPhone = (string) data_get($transaction?->request_payload, 'phone', $transaction?->customer_phone);
        $checks['airtime_recipient_match'] = $this->evidenceCheck(
            $run->intended_phone === null
                || $run->intended_phone === ''
                || ($transaction !== null && $this->phonesMatch($run->intended_phone, $recipientPhone)),
            'Airtime recipient matches intended phone.',
            'Airtime recipient mismatch.',
        );

        $imbalances = $transaction
            ? in_array($transaction->id, $this->financialLedgerService->imbalanceTransactions(), true)
            : true;

        $checks['ledger_balanced'] = $this->evidenceCheck(
            $transaction !== null && ! $imbalances,
            'Ledger postings are balanced.',
            'Ledger imbalance detected.',
        );
        $checks['payment_received_posting'] = $this->evidenceCheck(
            $transaction !== null && $this->financialLedgerService->hasPosting($transaction->id, LedgerEventType::PAYMENT_RECEIVED),
            'payment_received posting exists.',
            'payment_received posting missing.',
        );
        $checks['gateway_fee_recorded_posting'] = $this->evidenceCheck(
            $transaction !== null && $this->financialLedgerService->hasPosting($transaction->id, LedgerEventType::GATEWAY_FEE_RECORDED),
            'gateway_fee_recorded posting exists.',
            'gateway_fee_recorded posting missing.',
        );
        $checks['customer_funds_recognized_posting'] = $this->evidenceCheck(
            $transaction !== null
                && ($transaction->status !== TransactionStatus::FULFILLED
                    || $this->financialLedgerService->hasPosting($transaction->id, LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED)),
            'customer_funds_recognized posting exists after fulfillment.',
            'customer_funds_recognized posting missing.',
        );
        $checks['marketing_subsidy_absent'] = $this->evidenceCheck(
            $transaction !== null && (int) ($transaction->voucher_discount_amount ?? 0) === 0,
            'No marketing subsidy on base certification transaction.',
            'Voucher subsidy detected on certification transaction.',
        );

        $settlementStatus = (string) ($transaction?->financial?->settlement_status ?? '');
        $checks['settlement_expectation'] = $this->evidenceCheck(
            $transaction !== null && $settlementStatus !== '',
            'Settlement expectation recorded.',
            'Settlement expectation missing.',
        );

        $reconcile = $this->opsReliabilityService->snapshot()['reconciliation'] ?? [];
        $checks['reconciliation_clean'] = $this->evidenceCheck(
            $transaction !== null
                && ((int) ($reconcile['paid_unfulfilled'] ?? 0) === 0 || $transaction->status === TransactionStatus::FULFILLED),
            'Reconciliation shows no unexplained mismatch for this transaction state.',
            'Reconciliation mismatch may exist.',
        );

        $checks['receipt_accessible'] = $this->evidenceCheck(
            $transaction !== null && ($transaction->receipt_verification_token ?? '') !== '',
            'Receipt verification token present.',
            'Receipt not available yet.',
        );
        $checks['qr_verification'] = $this->evidenceCheck(
            $transaction !== null && ($transaction->receipt_verification_token ?? '') !== '',
            'QR verification token available.',
            'QR verification token missing.',
        );
        $checks['ops_visibility'] = $this->evidenceCheck(
            $transaction !== null,
            'Transaction visible to Ops.',
            'Transaction not linked.',
        );
        $checks['finance_center_visibility'] = $this->evidenceCheck(
            $transaction !== null && $this->financialLedgerService->hasPosting($transaction->id, LedgerEventType::PAYMENT_RECEIVED),
            'Finance Center ledger evidence exists.',
            'Finance Center ledger evidence missing.',
        );

        $criticalAlerts = $this->financialAlertService->scan(dryRun: true)['totals']['critical'] ?? 0;
        $checks['monitoring_alerts'] = $this->evidenceCheck(
            (int) $criticalAlerts === 0,
            'No active critical finance alerts.',
            'Critical finance alerts detected.',
        );

        return [
            'run_id' => $run->id,
            'reference' => $run->reference,
            'generated_at' => now()->toIso8601String(),
            'checks' => $checks,
            'summary' => [
                'pass' => collect($checks)->where('status', 'PASS')->count(),
                'warn' => collect($checks)->where('status', 'WARN')->count(),
                'fail' => collect($checks)->where('status', 'FAIL')->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, string|null>
     */
    private function deriveStatuses(array $evidence): array
    {
        $checks = is_array($evidence['checks'] ?? null) ? $evidence['checks'] : [];

        return [
            'payment_status' => $this->statusFromChecks($checks, [
                'paystack_verification_success',
                'payment_status_advanced',
                'paid_amount_match',
            ]),
            'fulfillment_status' => $this->statusFromChecks($checks, [
                'fulfillment_succeeded_once',
                'fulfillment_initiated_once',
            ]),
            'ledger_status' => $this->statusFromChecks($checks, [
                'ledger_balanced',
                'payment_received_posting',
                'gateway_fee_recorded_posting',
                'customer_funds_recognized_posting',
            ]),
            'reconciliation_status' => $this->statusFromChecks($checks, ['reconciliation_clean']),
            'settlement_expectation_status' => $this->statusFromChecks($checks, ['settlement_expectation']),
            'receipt_status' => $this->statusFromChecks($checks, ['receipt_accessible', 'qr_verification']),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     * @param  list<string>  $keys
     */
    private function statusFromChecks(array $checks, array $keys): ?string
    {
        $selected = collect($keys)
            ->map(fn (string $key) => $checks[$key]['status'] ?? 'WARN')
            ->values();

        if ($selected->contains('FAIL')) {
            return 'FAILED';
        }

        if ($selected->contains('WARN')) {
            return 'PENDING';
        }

        return $selected->every(fn (string $status) => $status === 'PASS') ? 'PASSED' : 'PENDING';
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function deriveResult(array $evidence, bool $finalized): string
    {
        $checks = is_array($evidence['checks'] ?? null) ? $evidence['checks'] : [];
        $failCount = collect($checks)->where('status', 'FAIL')->count();
        $warnCount = collect($checks)->where('status', 'WARN')->count();
        $passCount = collect($checks)->where('status', 'PASS')->count();

        if ($passCount === 0 && ! $finalized) {
            return PaymentCertificationRun::RESULT_INCOMPLETE;
        }

        if ($failCount > 0) {
            return $finalized ? PaymentCertificationRun::RESULT_FAILED : PaymentCertificationRun::RESULT_INCOMPLETE;
        }

        if ($warnCount > 0) {
            return $finalized
                ? PaymentCertificationRun::RESULT_CERTIFIED_WITH_WARNINGS
                : PaymentCertificationRun::RESULT_INCOMPLETE;
        }

        return $finalized
            ? PaymentCertificationRun::RESULT_CERTIFIED
            : PaymentCertificationRun::RESULT_INCOMPLETE;
    }

    /**
     * @return array{status: string, message: string}
     */
    private function evidenceCheck(bool $passed, string $passMessage, string $failMessage): array
    {
        return [
            'status' => $passed ? 'PASS' : 'WARN',
            'message' => $passed ? $passMessage : $failMessage,
        ];
    }

    private function phonesMatch(string $expected, string $actual): bool
    {
        $normalize = static fn (string $phone): string => preg_replace('/\D/', '', $phone) ?? '';

        return $normalize($expected) === $normalize($actual);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRun(PaymentCertificationRun $run): array
    {
        return [
            'id' => $run->id,
            'reference' => $run->reference,
            'environment' => $run->environment,
            'paystack_mode' => $run->paystack_mode,
            'provider_mode' => $run->provider_mode,
            'intended_product_type' => $run->intended_product_type,
            'intended_product_amount_kobo' => $run->intended_product_amount_kobo,
            'expected_convenience_fee_kobo' => $run->expected_convenience_fee_kobo,
            'expected_gateway_fee_kobo' => $run->expected_gateway_fee_kobo,
            'expected_total_kobo' => $run->expected_total_kobo,
            'intended_phone' => $run->intended_phone,
            'intended_network' => $run->intended_network,
            'transaction_id' => $run->transaction_id,
            'payment_status' => $run->payment_status,
            'fulfillment_status' => $run->fulfillment_status,
            'ledger_status' => $run->ledger_status,
            'reconciliation_status' => $run->reconciliation_status,
            'settlement_expectation_status' => $run->settlement_expectation_status,
            'receipt_status' => $run->receipt_status,
            'started_by' => $run->started_by,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'result' => $run->result,
            'notes' => $run->notes,
            'evidence' => $run->evidence_json,
            'is_active' => $run->isActive(),
        ];
    }
}
