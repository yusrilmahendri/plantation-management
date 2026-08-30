<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FertilizerApplicationItem extends Model
{
    protected $fillable = [
        'fertilizer_application_id',
        'inventory_item_id',
        'quantity',
        'dosage_per_plant',
        'plant_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'dosage_per_plant' => 'decimal:3',
            'plant_count' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(FertilizerApplication::class, 'fertilizer_application_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
