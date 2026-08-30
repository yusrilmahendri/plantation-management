<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceBudgetAllocation extends Model
{
    use BelongsToPlantationEntity;
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'finance_budget_public_id',
        'name',
        'period_start',
        'period_end',
        'allocated_amount',
        'status',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'allocated_amount' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetAllocationItem::class);
    }

    public function itemsAllocatedTotal(): string
    {
        return (string) $this->items()->sum('allocated_amount');
    }

    public function remainingToAllocate(): string
    {
        return bcsub((string) $this->allocated_amount, $this->itemsAllocatedTotal(), 2);
    }
}
