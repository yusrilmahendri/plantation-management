<?php

namespace App\Models;

use App\Enums\RealizationSourceType;
use App\Enums\RealizationStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetRealization extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'budget_allocation_item_id',
        'source_type',
        'source_public_id',
        'amount',
        'realization_date',
        'description',
        'status',
        'reversed_at',
        'reversed_reason',
        'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => RealizationSourceType::class,
            'amount' => 'decimal:2',
            'realization_date' => 'date',
            'status' => RealizationStatus::class,
            'reversed_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocationItem::class, 'budget_allocation_item_id');
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RealizationStatus::ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === RealizationStatus::ACTIVE;
    }
}
