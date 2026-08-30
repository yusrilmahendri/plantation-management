<?php

namespace App\Models;

use App\Enums\BudgetItemCategory;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetAllocationItem extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'finance_budget_allocation_id',
        'category',
        'name',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'category' => BudgetItemCategory::class,
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(FinanceBudgetAllocation::class, 'finance_budget_allocation_id');
    }

    public function realizations(): HasMany
    {
        return $this->hasMany(BudgetRealization::class);
    }

    public function realizedTotal(): string
    {
        return (string) $this->realizations()->active()->sum('amount');
    }

    public function remainingToRealize(): string
    {
        return bcsub((string) $this->allocated_amount, $this->realizedTotal(), 2);
    }
}
