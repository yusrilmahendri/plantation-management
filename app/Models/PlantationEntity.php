<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\PlantationEntityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PlantationEntity extends Model
{
    /** @use HasFactory<PlantationEntityFactory> */
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'name',
        'slug',
        'finance_entity_public_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function plantations(): HasMany
    {
        return $this->hasMany(Plantation::class);
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    public function workTypes(): HasMany
    {
        return $this->hasMany(WorkType::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function workActivities(): HasMany
    {
        return $this->hasMany(WorkActivity::class);
    }

    public function workerPayrolls(): HasMany
    {
        return $this->hasMany(WorkerPayroll::class);
    }

    public function inventoryPurchases(): HasMany
    {
        return $this->hasMany(InventoryPurchase::class);
    }

    public function materialUsages(): HasMany
    {
        return $this->hasMany(MaterialUsage::class);
    }

    public function fertilizerApplications(): HasMany
    {
        return $this->hasMany(FertilizerApplication::class);
    }

    public function buyers(): HasMany
    {
        return $this->hasMany(Buyer::class);
    }

    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    public function harvestSales(): HasMany
    {
        return $this->hasMany(HarvestSale::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(PlantationAccessToken::class);
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'entity';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
