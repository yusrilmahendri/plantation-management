<?php

namespace App\Console\Commands;

use App\Jobs\DispatchIntegrationOutboxEvent;
use App\Services\IntegrationOutboxService;
use Illuminate\Console\Command;

class DispatchIntegrationOutboxCommand extends Command
{
    protected $signature = 'integration:dispatch-outbox {--limit=50}';

    protected $description = 'Claim pending Finance integration outbox rows and dispatch queue jobs.';

    public function handle(IntegrationOutboxService $outbox): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $ids = $outbox->claimDue($limit);

        foreach ($ids as $id) {
            DispatchIntegrationOutboxEvent::dispatch($id)
                ->onQueue((string) config('services.integration.queue', 'integrations'));
        }

        $this->info('Dispatched '.count($ids).' integration outbox job(s).');

        return self::SUCCESS;
    }
}
