<?php

namespace App\Models;

use App\Enums\ProductionDocumentStatus;
use App\Enums\SalePaymentStatus;
use App\Models\Concerns\BelongsToPlantationEntity;
use App\Models\Concerns\HasPublicUlid;
use App\Services\HarvestSalePaymentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HarvestSale extends Model
{
    use BelongsToPlantationEntity;
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'plantation_entity_id',
        'buyer_id',
        'sale_date',
        'invoice_number',
        'description',
        'subtotal',
        'adjustment_amount',
        'total_amount',
        'status',
        'payment_status',
        'cancelled_reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'subtotal' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => ProductionDocumentStatus::class,
            'payment_status' => SalePaymentStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HarvestSaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(HarvestSalePayment::class);
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

    public function paidAmount(): string
    {
        return app(HarvestSalePaymentService::class)->paidAmount($this);
    }

    public function outstandingAmount(): string
    {
        return app(HarvestSalePaymentService::class)->outstandingAmount($this);
    }

    public function refreshPaymentStatus(): void
    {
        app(HarvestSalePaymentService::class)->refreshPaymentStatus($this);
    }
}
