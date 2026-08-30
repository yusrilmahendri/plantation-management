<?php

namespace App\Services;

use App\Enums\ProductionDocumentStatus;
use App\Models\Harvest;
use App\Models\HarvestSaleItem;
use App\Support\Quantity;
use InvalidArgumentException;

class HarvestAvailabilityService
{
    public function soldQuantity(Harvest $harvest): string
    {
        $sold = HarvestSaleItem::query()
            ->where('harvest_id', $harvest->id)
            ->whereHas('sale', fn ($query) => $query->where('status', ProductionDocumentStatus::POSTED))
            ->sum('quantity');

        return Quantity::normalize($sold ?: '0');
    }

    public function availableQuantity(Harvest $harvest): string
    {
        if (! $harvest->isPosted()) {
            return Quantity::normalize('0');
        }

        return Quantity::sub($harvest->quantity, $this->soldQuantity($harvest));
    }

    public function assertCanSell(Harvest $harvest, mixed $quantity): void
    {
        if (! $harvest->isPosted()) {
            throw new InvalidArgumentException('Hanya panen yang sudah diposting yang dapat dijual.');
        }

        $qty = Quantity::normalize($quantity);
        if (! Quantity::isPositive($qty)) {
            throw new InvalidArgumentException('Kuantitas penjualan harus lebih dari 0.');
        }

        $available = $this->availableQuantity($harvest);
        if (Quantity::cmp($qty, $available) === 1) {
            throw new InvalidArgumentException(
                'Kuantitas penjualan melebihi hasil panen yang tersedia ('.$available.' '.$harvest->unit.').'
            );
        }
    }

    public function hasPostedSales(Harvest $harvest): bool
    {
        return Quantity::cmp($this->soldQuantity($harvest), '0') === 1;
    }
}
