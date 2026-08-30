<?php

namespace App\Models;

use App\Enums\InventoryDocumentStatus;
use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FertilizerApplication extends Model
{
    use BelongsToPlantationEntity;
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'plantation_id',
        'plantation_block_id',
        'application_date',
        'work_activity_id',
        'notes',
        'status',
        'cancelled_reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
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

    public function workActivity(): BelongsTo
    {
        return $this->belongsTo(WorkActivity::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FertilizerApplicationItem::class);
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
