<?php

namespace App\Console\Commands;

use App\Models\PaymentCertificationRun;
use App\Services\Launch\LaunchAuditService;
use App\Services\Launch\LaunchModeService;
use App\Services\Launch\PaymentCertificationService;
use Illuminate\Console\Command;

class PaylityPaymentCertifyLiveCommand extends Command
{
    protected $signature = 'paylity:payment-certify-live
                            {--reference= : Link an existing transaction reference}
                            {--product=airtime : Intended product type}
                            {--amount=100 : Intended product amount in naira}
                            {--phone= : Intended airtime recipient phone}
                            {--network= : Intended network for airtime}
                            {--run= : Existing certification run id}
                            {--inspect-only : Inspect active or specified run without creating a new session}
                            {--finalize : Finalize certification after evidence refresh}
                            {--force : Allow creating a session when another active run exists}
                            {--json : Output JSON only}';

    protected $description = 'Manage live payment certification sessions without charging cards';

    public function __construct(
        private readonly PaymentCertificationService $paymentCertificationService,
        private readonly LaunchAuditService $launchAuditService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $run = $this->resolveRun();

        if ($run === null && ! $this->option('inspect-only')) {
            $run = PaymentCertificationRun::query()->find(
                data_get(
                    $this->paymentCertificationService->createSession(
                        productType: (string) $this->option('product'),
                        productAmountNaira: (int) $this->option('amount'),
                        phone: $this->option('phone') ? (string) $this->option('phone') : null,
                        network: $this->option('network') ? (string) $this->option('network') : null,
                        operator: 'cli',
                        force: (bool) $this->option('force'),
                    ),
                    'id',
                ),
            );

            if ($run instanceof PaymentCertificationRun) {
                $this->launchAuditService->record(
                    action: LaunchAuditService::ACTION_CERTIFICATION_CREATED,
                    new: ['run_id' => $run->id, 'product' => $run->intended_product_type],
                    operator: 'cli',
                    runId: $run->id,
                );
            }
        }

        if (! $run instanceof PaymentCertificationRun) {
            $this->error('No certification run available.');

            return self::FAILURE;
        }

        if ($this->option('reference')) {
            $payload = $this->paymentCertificationService->linkReference(
                $run,
                (string) $this->option('reference'),
                'cli',
            );
            $this->launchAuditService->record(
                action: LaunchAuditService::ACTION_CERTIFICATION_LINKED,
                new: ['reference' => (string) $this->option('reference')],
                operator: 'cli',
                reference: (string) $this->option('reference'),
                runId: $run->id,
            );
            $run = PaymentCertificationRun::query()->findOrFail($payload['id']);
        } else {
            $payload = $this->paymentCertificationService->refreshRun($run, 'cli');
        }

        if ($this->option('finalize')) {
            $payload = $this->paymentCertificationService->finalize($run->fresh(), 'cli');
            $this->launchAuditService->record(
                action: LaunchAuditService::ACTION_CERTIFICATION_FINALIZED,
                new: ['result' => $payload['result'] ?? PaymentCertificationRun::RESULT_INCOMPLETE],
                operator: 'cli',
                runId: (int) ($payload['id'] ?? $run->id),
            );
        }

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        } else {
            $this->info('Live Payment Certification — '.($payload['result'] ?? PaymentCertificationRun::RESULT_INCOMPLETE));
            $this->line('Run ID: '.($payload['id'] ?? '—'));
            $this->line('Reference: '.($payload['reference'] ?? '—'));
            $this->line('Payment: '.($payload['payment_status'] ?? '—'));
            $this->line('Fulfillment: '.($payload['fulfillment_status'] ?? '—'));
            $this->line('Ledger: '.($payload['ledger_status'] ?? '—'));
        }

        $result = (string) ($payload['result'] ?? PaymentCertificationRun::RESULT_INCOMPLETE);

        return in_array($result, [
            PaymentCertificationRun::RESULT_CERTIFIED,
            PaymentCertificationRun::RESULT_CERTIFIED_WITH_WARNINGS,
            PaymentCertificationRun::RESULT_INCOMPLETE,
        ], true) ? self::SUCCESS : self::FAILURE;
    }

    private function resolveRun(): ?PaymentCertificationRun
    {
        if ($this->option('run')) {
            return $this->paymentCertificationService->findRun((int) $this->option('run'));
        }

        if ($this->option('inspect-only') || $this->option('reference') || $this->option('finalize')) {
            return $this->paymentCertificationService->activeRun();
        }

        return null;
    }
}
