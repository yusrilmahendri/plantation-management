<?php

namespace App\Jobs;

use App\Services\IntegrationOutboxService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchIntegrationOutboxEvent implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public function __construct(public int $outboxId)
    {
        $this->onQueue((string) config('services.integration.queue', 'integrations'));
    }

    public function uniqueId(): string
    {
        return 'integration-outbox-'.$this->outboxId;
    }

    public function handle(IntegrationOutboxService $outbox): void
    {
        $outbox->process($this->outboxId);
    }
}
