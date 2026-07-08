<?php

namespace App\Console\Commands;

use App\Services\Fulfillment\FulfillmentRetryService;
use Illuminate\Console\Command;

class PaylityProcessFulfillmentRetriesCommand extends Command
{
    protected $signature = 'paylity:process-fulfillment-retries';

    protected $description = 'Process due automated fulfillment retries and escalate exhausted attempts';

    public function __construct(
        private readonly FulfillmentRetryService $fulfillmentRetryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $summary = $this->fulfillmentRetryService->processDueRetries();

        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn (int $count, string $metric) => [$metric, $count])->values()->all(),
        );

        return self::SUCCESS;
    }
}
