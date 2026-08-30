<?php

namespace App\Models;

use App\Enums\InventoryCategory;
use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use BelongsToPlantationEntity, HasFactory, HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'name',
        'sku',
        'category',
        'unit',
        'minimum_stock',
        'last_unit_cost',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => InventoryCategory::class,
            'minimum_stock' => 'decimal:3',
            'last_unit_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(InventoryPurchaseItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function usageItems(): HasMany
    {
        return $this->hasMany(MaterialUsageItem::class);
    }

    public function fertilizerItems(): HasMany
    {
        return $this->hasMany(FertilizerApplicationItem::class);
    }

    public function hasOperationalHistory(): bool
    {
        return $this->purchaseItems()->exists()
            || $this->stockMovements()->exists()
            || $this->usageItems()->exists()
            || $this->fertilizerItems()->exists();
    }
}
