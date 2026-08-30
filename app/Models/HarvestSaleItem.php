<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HarvestSaleItem extends Model
{
    protected $fillable = [
        'harvest_sale_id',
        'harvest_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(HarvestSale::class, 'harvest_sale_id');
    }

    public function harvest(): BelongsTo
    {
        return $this->belongsTo(Harvest::class);
    }
}
