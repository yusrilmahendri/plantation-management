<?php

namespace App\Models;

use App\Enums\InventoryDocumentStatus;
use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialUsage extends Model
{
    use BelongsToPlantationEntity;
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'plantation_id',
        'plantation_block_id',
        'usage_date',
        'description',
        'status',
        'cancelled_reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'status' => InventoryDocumentStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function plantation(): BelongsTo
    {
        return $this->belongsTo(Plantation::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(PlantationBlock::class, 'plantation_block_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialUsageItem::class);
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
