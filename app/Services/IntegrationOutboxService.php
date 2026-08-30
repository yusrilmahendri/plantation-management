<?php

namespace App\Services;

use App\Enums\IntegrationEventType;
use App\Enums\IntegrationOutboxStatus;
use App\Exceptions\FinanceIntegrationException;
use App\Jobs\DispatchIntegrationOutboxEvent;
use App\Models\HarvestSale;
use App\Models\HarvestSalePayment;
use App\Models\IntegrationOutbox;
use App\Models\InventoryPurchase;
use App\Models\PlantationEntity;
use App\Models\WorkerPayroll;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IntegrationOutboxService
{
    public function __construct(private readonly FinanceIntegrationClient $client) {}

    public function recordPurchasePosted(InventoryPurchase $purchase): ?IntegrationOutbox
    {
        $purchase->loadMissing(['items.inventoryItem', 'supplier', 'plantationEntity']);

        $categories = $purchase->items
            ->map(fn ($line) => $line->inventoryItem?->category?->value)
            ->filter()
            ->unique()
            ->values();

        return $this->record(
            IntegrationEventType::PLANTATION_PURCHASE_POSTED,
            $purchase->plantationEntity,
            $purchase->public_id,
            [
                'purchase_public_id' => $purchase->public_id,
                'purchase_date' => $purchase->purchase_date?->toDateString(),
                'description' => $purchase->description,
                'invoice_number' => $purchase->invoice_number,
                'supplier' => [
                    'public_id' => $purchase->supplier?->public_id,
                    'name' => $purchase->supplier?->name,
                ],
                'amount' => (string) $purchase->total_amount,
                'category' => $categories->count() > 1 ? 'MIXED' : ($categories->first() ?: 'OTHER'),
                'line_items' => $purchase->items->map(fn ($line) => [
                    'inventory_item_public_id' => $line->inventoryItem?->public_id,
                    'name' => $line->inventoryItem?->name,
                    'category' => $line->inventoryItem?->category?->value,
                    'quantity' => (string) $line->quantity,
                    'unit_cost' => (string) $line->unit_cost,
                    'line_total' => (string) $line->line_total,
                ])->values()->all(),
            ],
        );
    }

    public function recordPurchaseCancelled(InventoryPurchase $purchase): ?IntegrationOutbox
    {
        return $this->record(
            IntegrationEventType::PLANTATION_PURCHASE_CANCELLED,
            $purchase->plantationEntity,
            $purchase->public_id,
            [
                'purchase_public_id' => $purchase->public_id,
                'cancelled_reason' => $purchase->cancelled_reason,
            ],
        );
    }

    public function recordPayrollPaid(WorkerPayroll $payroll): ?IntegrationOutbox
    {
        $payroll->loadMissing(['worker', 'activity', 'workType', 'plantationEntity']);

        return $this->record(
            IntegrationEventType::PLANTATION_PAYROLL_PAID,
            $payroll->plantationEntity,
            $payroll->public_id,
            [
                'payroll_public_id' => $payroll->public_id,
                'worker_public_id' => $payroll->worker?->public_id,
                'worker_name' => $payroll->worker?->name,
                'work_activity_public_id' => $payroll->activity?->public_id,
                'work_activity_title' => $payroll->activity?->title,
                'work_type' => $payroll->workType?->name,
                'activity_date' => $payroll->activity?->activity_date?->toDateString(),
                'paid_at' => $payroll->paid_at?->toIso8601String(),
                'payment_method' => $payroll->payment_method?->value ?? 'CASH',
                'amount' => (string) $payroll->final_amount,
            ],
        );
    }

    public function recordSalePosted(HarvestSale $sale): ?IntegrationOutbox
    {
        $sale->loadMissing(['buyer', 'items.harvest', 'plantationEntity']);

        return $this->record(
            IntegrationEventType::HARVEST_SALE_POSTED,
            $sale->plantationEntity,
            $sale->public_id,
            [
                'sale_public_id' => $sale->public_id,
                'sale_date' => $sale->sale_date?->toDateString(),
                'invoice_number' => $sale->invoice_number,
                'buyer' => [
                    'public_id' => $sale->buyer?->public_id,
                    'name' => $sale->buyer?->name,
                ],
                'total_amount' => (string) $sale->total_amount,
                'description' => $sale->description,
                'items' => $sale->items->map(fn ($line) => [
                    'commodity' => $line->harvest?->commodity?->value,
                    'quantity' => (string) $line->quantity,
                    'unit' => $line->harvest?->unit,
                    'unit_price' => (string) $line->unit_price,
                    'line_total' => (string) $line->line_total,
                ])->values()->all(),
            ],
        );
    }

    public function recordSaleCancelled(HarvestSale $sale): ?IntegrationOutbox
    {
        return $this->record(
            IntegrationEventType::HARVEST_SALE_CANCELLED,
            $sale->plantationEntity,
            $sale->public_id,
            [
                'sale_public_id' => $sale->public_id,
                'cancelled_reason' => $sale->cancelled_reason,
            ],
        );
    }

    public function recordSalePaymentReceived(HarvestSalePayment $payment): ?IntegrationOutbox
    {
        $payment->loadMissing(['sale.plantationEntity']);
        $sale = $payment->sale;

        return $this->record(
            IntegrationEventType::HARVEST_SALE_PAYMENT_RECEIVED,
            $sale?->plantationEntity,
            $payment->public_id,
            [
                'payment_public_id' => $payment->public_id,
                'sale_public_id' => $sale?->public_id,
                'payment_date' => $payment->payment_date?->toDateString(),
                'payment_method' => $payment->payment_method?->value,
                'reference_number' => $payment->reference_number,
                'amount' => (string) $payment->amount,
            ],
        );
    }

    public function recordSalePaymentReversed(HarvestSalePayment $payment): ?IntegrationOutbox
    {
        $payment->loadMissing(['sale.plantationEntity']);
        $sale = $payment->sale;

        return $this->record(
            IntegrationEventType::HARVEST_SALE_PAYMENT_REVERSED,
            $sale?->plantationEntity,
            $payment->public_id,
            [
                'payment_public_id' => $payment->public_id,
                'sale_public_id' => $sale?->public_id,
                'amount' => (string) $payment->amount,
                'reversed_reason' => $payment->reversed_reason,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        IntegrationEventType $type,
        ?PlantationEntity $entity,
        string $sourcePublicId,
        array $payload,
    ): ?IntegrationOutbox {
        if (! (bool) config('services.integration.events_enabled')) {
            return null;
        }

        if (! $entity instanceof PlantationEntity) {
            return null;
        }

        $financeId = trim((string) $entity->finance_entity_public_id);
        if ($financeId === '') {
            return null;
        }

        $existing = IntegrationOutbox::query()
            ->where('event_type', $type)
            ->where('source_public_id', $sourcePublicId)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof IntegrationOutbox) {
            return $existing;
        }

        $outbox = IntegrationOutbox::query()->create([
            'event_id' => (string) Str::ulid(),
            'event_type' => $type,
            'event_version' => (int) config('services.integration.event_version', 1),
            'plantation_entity_public_id' => $entity->public_id,
            'finance_entity_public_id' => $financeId,
            'source_public_id' => $sourcePublicId,
            'payload' => $payload,
            'status' => IntegrationOutboxStatus::PENDING,
            'attempts' => 0,
            'available_at' => now(),
        ]);

        DB::afterCommit(function () use ($outbox): void {
            DispatchIntegrationOutboxEvent::dispatch($outbox->id)
                ->onQueue((string) config('services.integration.queue', 'integrations'));
        });

        return $outbox;
    }

    /**
     * @return list<int>
     */
    public function claimDue(int $limit = 50): array
    {
        return DB::transaction(function () use ($limit) {
            $rows = IntegrationOutbox::query()
                ->due()
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $ids = [];
            foreach ($rows as $row) {
                $row->update([
                    'status' => IntegrationOutboxStatus::PROCESSING,
                    'dispatched_at' => now(),
                ]);
                $ids[] = (int) $row->id;
            }

            return $ids;
        });
    }

    public function process(int $outboxId): void
    {
        $row = DB::transaction(function () use ($outboxId): ?IntegrationOutbox {
            $locked = IntegrationOutbox::query()->whereKey($outboxId)->lockForUpdate()->first();

            if (! $locked instanceof IntegrationOutbox) {
                return null;
            }

            if ($locked->status === IntegrationOutboxStatus::SENT || $locked->status === IntegrationOutboxStatus::FAILED) {
                return null;
            }

            $locked->update([
                'status' => IntegrationOutboxStatus::PROCESSING,
                'last_attempt_at' => now(),
                'dispatched_at' => $locked->dispatched_at ?? now(),
            ]);

            return $locked->fresh();
        });

        if (! $row instanceof IntegrationOutbox) {
            return;
        }

        try {
            $result = $this->client->send($row->envelope());
            $already = (bool) ($result['already_processed'] ?? false);

            DB::transaction(function () use ($row, $already): void {
                $locked = IntegrationOutbox::query()->whereKey($row->id)->lockForUpdate()->first();

                if (! $locked instanceof IntegrationOutbox || $locked->status === IntegrationOutboxStatus::SENT) {
                    return;
                }

                $locked->update([
                    'status' => IntegrationOutboxStatus::SENT,
                    'processed_at' => now(),
                    'last_error' => null,
                    'attempts' => $locked->attempts + 1,
                ]);

                if ($already) {
                    Log::info('finance.integration_already_processed', [
                        'event_type' => $locked->event_type->value,
                        'event_id' => $locked->event_id,
                    ]);
                }
            });
        } catch (FinanceIntegrationException $exception) {
            DB::transaction(function () use ($row, $exception): void {
                $locked = IntegrationOutbox::query()->whereKey($row->id)->lockForUpdate()->first();

                if ($locked instanceof IntegrationOutbox) {
                    $this->markAttempt($locked, $exception);
                }
            });
        }
    }

    public function retryFailed(int $outboxId): IntegrationOutbox
    {
        $row = DB::transaction(function () use ($outboxId): IntegrationOutbox {
            $locked = IntegrationOutbox::query()->whereKey($outboxId)->lockForUpdate()->firstOrFail();

            if ($locked->status !== IntegrationOutboxStatus::FAILED) {
                return $locked;
            }

            $locked->update([
                'status' => IntegrationOutboxStatus::PENDING,
                'available_at' => now(),
                'last_error' => null,
            ]);

            return $locked->fresh() ?? $locked;
        });

        if ($row->status === IntegrationOutboxStatus::PENDING) {
            DispatchIntegrationOutboxEvent::dispatch($row->id)
                ->onQueue((string) config('services.integration.queue', 'integrations'));
        }

        return $row;
    }

    public function pruneSent(int $retentionDays = 90): int
    {
        return IntegrationOutbox::query()
            ->where('status', IntegrationOutboxStatus::SENT)
            ->where('processed_at', '<', now()->subDays($retentionDays))
            ->delete();
    }

    private function markAttempt(IntegrationOutbox $row, FinanceIntegrationException $exception): void
    {
        $attempts = $row->attempts + 1;
        $max = (int) config('services.integration.max_attempts', 8);
        $error = $this->sanitizeError($exception->getMessage());

        if (! $exception->retryable || $attempts >= $max) {
            $row->update([
                'status' => IntegrationOutboxStatus::FAILED,
                'attempts' => $attempts,
                'last_error' => $error,
                'last_attempt_at' => now(),
            ]);

            return;
        }

        $row->update([
            'status' => IntegrationOutboxStatus::PENDING,
            'attempts' => $attempts,
            'available_at' => now()->addSeconds($this->backoffSeconds($attempts)),
            'last_error' => $error,
            'last_attempt_at' => now(),
        ]);
    }

    private function backoffSeconds(int $attempts): int
    {
        $schedule = config('services.integration.backoff', [60, 300, 900, 3600]);

        if (! is_array($schedule) || $schedule === []) {
            return 60;
        }

        $index = min($attempts - 1, count($schedule) - 1);

        return (int) $schedule[$index];
    }

    private function sanitizeError(string $message): string
    {
        $blocked = [
            (string) config('services.finance.service_token'),
            (string) config('services.finance.hmac_secret'),
        ];

        foreach ($blocked as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[redacted]', $message);
            }
        }

        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }
}
