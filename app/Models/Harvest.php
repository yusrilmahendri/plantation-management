<?php

namespace App\Models;

use App\Enums\Commodity;
use App\Enums\ProductionDocumentStatus;
use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Harvest extends Model
{
    use BelongsToPlantationEntity;
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'plantation_id',
        'plantation_block_id',
        'work_activity_id',
        'harvest_date',
        'commodity',
        'quantity',
        'unit',
        'bunch_count',
        'quality_grade',
        'notes',
        'status',
        'cancelled_reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'harvest_date' => 'date',
            'commodity' => Commodity::class,
            'quantity' => 'decimal:3',
            'bunch_count' => 'integer',
            'status' => ProductionDocumentStatus::class,
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

    public function saleItems(): HasMany
    {
        return $this->hasMany(HarvestSaleItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === ProductionDocumentStatus::DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === ProductionDocumentStatus::POSTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ProductionDocumentStatus::CANCELLED;
    }
}
