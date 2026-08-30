<?php

namespace App\Console\Commands;

use App\Services\IntegrationOutboxService;
use Illuminate\Console\Command;

class PruneIntegrationOutboxCommand extends Command
{
    protected $signature = 'integration:prune-outbox {--days=}';

    protected $description = 'Delete SENT integration outbox rows older than the retention window.';

    public function handle(IntegrationOutboxService $outbox): int
    {
        $days = (int) ($this->option('days') ?: config('services.integration.outbox_retention_days', 90));
        $deleted = $outbox->pruneSent(max(1, $days));
        $this->info('Pruned '.$deleted.' SENT outbox row(s).');

        return self::SUCCESS;
    }
}
