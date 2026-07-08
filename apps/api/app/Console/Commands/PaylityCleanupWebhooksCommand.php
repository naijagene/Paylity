<?php

namespace App\Console\Commands;

use App\Services\WebhookEventService;
use Illuminate\Console\Command;

class PaylityCleanupWebhooksCommand extends Command
{
    protected $signature = 'paylity:cleanup-webhooks {--days=90 : Delete processed webhook events older than this many days}';

    protected $description = 'Purge old processed webhook events';

    public function __construct(
        private readonly WebhookEventService $webhookEventService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));

        $deleted = $this->webhookEventService->purgeProcessedOlderThanDays($days);

        $this->info("Deleted {$deleted} processed webhook event(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
