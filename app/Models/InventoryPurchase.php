<?php

namespace App\Models;

use App\Enums\InventoryDocumentStatus;
use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPurchase extends Model
{
    use BelongsToPlantationEntity;
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'supplier_id',
        'purchase_date',
        'invoice_number',
        'description',
        'subtotal',
        'adjustment_amount',
        'total_amount',
        'status',
        'budget_allocation_item_id',
        'budget_realization_id',
        'cancelled_reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => InventoryDocumentStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryPurchaseItem::class);
    }

    public function budgetAllocationItem(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocationItem::class);
    }

    public function budgetRealization(): BelongsTo
    {
        return $this->belongsTo(BudgetRealization::class);
    }

    public function isDraft(): bool
    {
        return $this->status === InventoryDocumentStatus::DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === InventoryDocumentStatus::POSTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === InventoryDocumentStatus::CANCELLED;
    }
}
