<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToPlantationEntity;
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'inventory_item_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'movement_date',
        'source_type',
        'source_public_id',
        'plantation_id',
        'plantation_block_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'movement_date' => 'date',
            'source_type' => StockSourceType::class,
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function plantation(): BelongsTo
    {
        return $this->belongsTo(Plantation::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(PlantationBlock::class, 'plantation_block_id');
    }
}
