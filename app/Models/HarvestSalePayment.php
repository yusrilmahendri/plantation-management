<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HarvestSalePayment extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'harvest_sale_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_number',
        'notes',
        'status',
        'reversed_at',
        'reversed_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentRecordStatus::class,
            'reversed_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(HarvestSale::class, 'harvest_sale_id');
    }

    public function isActive(): bool
    {
        return $this->status === PaymentRecordStatus::ACTIVE;
    }
}
