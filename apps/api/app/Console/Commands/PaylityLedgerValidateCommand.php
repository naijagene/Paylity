<?php

namespace App\Console\Commands;

use App\Services\Finance\LedgerProductionValidationService;
use Illuminate\Console\Command;

class PaylityLedgerValidateCommand extends Command
{
    protected $signature = 'paylity:ledger-validate
                            {--since= : Candidate query since date (ISO)}
                            {--date= : Candidate query created date (YYYY-MM-DD)}
                            {--limit=50 : Candidate query limit for exclusion analysis}';

    protected $description = 'Read-only ledger production validation against the active database';

    public function __construct(
        private readonly LedgerProductionValidationService $ledgerProductionValidationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->ledgerProductionValidationService->report(
            candidateLimit: max(1, (int) $this->option('limit')),
            since: $this->option('since') ? (string) $this->option('since') : null,
            date: $this->option('date') ? (string) $this->option('date') : null,
        );

        $this->info('STEP 1 — Transaction status breakdown');
        $this->line((string) data_get($report, 'step_1_status_breakdown.sql'));
        $this->table(
            ['status', 'count'],
            collect(data_get($report, 'step_1_status_breakdown.rows', []))
                ->map(fn (array $row) => [$row['status'], $row['count']])
                ->all(),
        );

        $this->newLine();
        $this->info('STEP 2 — Payment evidence counts');
        $this->table(
            ['Metric', 'Count'],
            [
                ['payment_reference IS NOT NULL', data_get($report, 'step_2_payment_evidence_counts.payment_reference_not_null', 0)],
                ["response_payload->verify.status = 'success'", data_get($report, 'step_2_payment_evidence_counts.verify_status_success', 0)],
                ["response_payload->webhook.data.status = 'success'", data_get($report, 'step_2_payment_evidence_counts.webhook_data_status_success', 0)],
            ],
        );

        $this->newLine();
        $this->info('STEP 3 — Fulfilled transaction ledger coverage');
        $fulfilledRows = data_get($report, 'step_3_fulfilled_transactions', []);
        if ($fulfilledRows === []) {
            $this->warn('No fulfilled transactions in this database.');
        } else {
            $this->table(
                ['reference', 'status', 'payment_reference', 'payment_posting', 'fulfillment_posting', 'needs_manual_review'],
                collect($fulfilledRows)->map(fn (array $row) => [
                    $row['reference'],
                    $row['status'],
                    $row['payment_reference'] ?? '—',
                    $row['ledger_payment_posting_exists'] ? 'yes' : 'no',
                    $row['ledger_fulfillment_posting_exists'] ? 'yes' : 'no',
                    $row['needs_manual_review'] ? 'yes' : 'no',
                ])->all(),
            );
        }

        $this->newLine();
        $this->info('STEP 4 — LedgerBackfillService candidate query');
        $candidate = data_get($report, 'step_4_candidate_query', []);
        $this->line('SQL: ' . (string) data_get($candidate, 'sql'));
        $this->line('Bindings: ' . json_encode(data_get($candidate, 'bindings', [])));
        $this->line('Interpolated: ' . (string) data_get($candidate, 'interpolated_sql'));
        $this->table(
            ['Inspection', 'Value'],
            [
                ['global_scopes_enabled', data_get($candidate, 'model_inspection.global_scopes_enabled') ? 'true' : 'false'],
                ['soft_deletes_enabled', data_get($candidate, 'model_inspection.soft_deletes_enabled') ? 'true' : 'false'],
                ['joins', data_get($candidate, 'model_inspection.joins')],
                ['eligible_statuses', implode(', ', (array) data_get($candidate, 'model_inspection.lifecycle_filters.eligible_statuses', []))],
            ],
        );

        $this->newLine();
        $this->info('STEP 5 — Eligible transactions excluded from candidate batch');
        $exclusions = data_get($report, 'step_5_eligible_exclusions', []);
        if ($exclusions === []) {
            $this->line('No eligible transactions were excluded by the candidate query predicate.');
        } else {
            $this->table(
                ['reference', 'status', 'condition', 'reason'],
                collect($exclusions)->map(fn (array $row) => [
                    $row['reference'],
                    $row['status'],
                    $row['condition'],
                    $row['reason'],
                ])->all(),
            );
        }

        $this->newLine();
        $this->info('STEP 6 — Root cause summary');
        $rootCause = data_get($report, 'step_6_root_cause', []);
        $this->table(
            ['Field', 'Value'],
            [
                ['database_connection', data_get($rootCause, 'database_connection')],
                ['database_name', data_get($rootCause, 'database_name')],
                ['total_transactions_in_database', data_get($rootCause, 'total_transactions_in_database')],
                ['eligible_in_database', data_get($rootCause, 'eligible_in_database')],
                ['ledger_posting_count', data_get($report, 'ledger_posting_count')],
                ['root_cause', data_get($rootCause, 'root_cause')],
            ],
        );
        $this->warn((string) data_get($rootCause, 'root_cause_detail'));

        return self::SUCCESS;
    }
}
