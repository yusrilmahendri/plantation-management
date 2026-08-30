<?php

namespace App\Models;

use App\Enums\IntegrationEventType;
use App\Enums\IntegrationOutboxStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IntegrationOutbox extends Model
{
    protected $table = 'integration_outbox';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'event_type',
        'event_version',
        'plantation_entity_public_id',
        'finance_entity_public_id',
        'source_public_id',
        'payload',
        'status',
        'attempts',
        'available_at',
        'dispatched_at',
        'processed_at',
        'last_attempt_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => IntegrationEventType::class,
            'event_version' => 'integer',
            'payload' => 'array',
            'status' => IntegrationOutboxStatus::class,
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'processed_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where(function (Builder $outer): void {
            $outer->where(function (Builder $pending): void {
                $pending->where('status', IntegrationOutboxStatus::PENDING)
                    ->where(function (Builder $inner): void {
                        $inner->whereNull('available_at')
                            ->orWhere('available_at', '<=', now());
                    });
            })->orWhere(function (Builder $stale): void {
                $stale->where('status', IntegrationOutboxStatus::PROCESSING)
                    ->where('dispatched_at', '<', now()->subMinutes(10));
            });
        });
    }

    public function getRouteKeyName(): string
    {
        return 'event_id';
    }

    /**
     * @return array<string, mixed>
     */
    public function envelope(): array
    {
        return [
            'event_id' => $this->event_id,
            'event_type' => $this->event_type->value,
            'event_version' => $this->event_version,
            'occurred_at' => $this->created_at?->toIso8601String(),
            'plantation_entity_public_id' => $this->plantation_entity_public_id,
            'finance_entity_public_id' => $this->finance_entity_public_id,
            'source_public_id' => $this->source_public_id,
            'payload' => $this->payload,
        ];
    }
}
