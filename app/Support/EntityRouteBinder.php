<?php

namespace App\Support;

use App\Models\BudgetAllocationItem;
use App\Models\BudgetRealization;
use App\Models\FertilizerApplication;
use App\Models\FinanceBudgetAllocation;
use App\Models\Harvest;
use App\Models\HarvestSale;
use App\Models\HarvestSalePayment;
use App\Models\Buyer;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\MaterialUsage;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\Supplier;
use App\Models\WorkActivity;
use App\Models\WorkAttendance;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Model;

class EntityRouteBinder
{
    public static function plantation(string $value): Plantation
    {
        return Plantation::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function plantationBlock(string $value): PlantationBlock
    {
        return PlantationBlock::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function worker(string $value): Worker
    {
        return Worker::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function workType(string $value): WorkType
    {
        return WorkType::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function supplier(string $value): Supplier
    {
        return Supplier::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function inventoryItem(string $value): InventoryItem
    {
        return InventoryItem::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function financeBudgetAllocation(string $value): FinanceBudgetAllocation
    {
        return FinanceBudgetAllocation::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function budgetAllocationItem(string $value): BudgetAllocationItem
    {
        return BudgetAllocationItem::query()
            ->where('public_id', $value)
            ->whereHas('allocation', fn ($query) => $query->forEntity(self::entity()))
            ->firstOrFail();
    }

    public static function budgetRealization(string $value): BudgetRealization
    {
        return BudgetRealization::query()
            ->where('public_id', $value)
            ->whereHas('item.allocation', fn ($query) => $query->forEntity(self::entity()))
            ->firstOrFail();
    }

    public static function workActivity(string $value): WorkActivity
    {
        return WorkActivity::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function workAttendance(string $value): WorkAttendance
    {
        return WorkAttendance::query()
            ->where('public_id', $value)
            ->whereHas('activity', fn ($query) => $query->forEntity(self::entity()))
            ->firstOrFail();
    }

    public static function workerPayroll(string $value): WorkerPayroll
    {
        return WorkerPayroll::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function inventoryPurchase(string $value): InventoryPurchase
    {
        return InventoryPurchase::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function materialUsage(string $value): MaterialUsage
    {
        return MaterialUsage::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function fertilizerApplication(string $value): FertilizerApplication
    {
        return FertilizerApplication::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function buyer(string $value): Buyer
    {
        return Buyer::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function harvest(string $value): Harvest
    {
        return Harvest::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function harvestSale(string $value): HarvestSale
    {
        return HarvestSale::query()
            ->forEntity(self::entity())
            ->where('public_id', $value)
            ->firstOrFail();
    }

    public static function harvestSalePayment(string $value): HarvestSalePayment
    {
        return HarvestSalePayment::query()
            ->where('public_id', $value)
            ->whereHas('sale', fn ($query) => $query->forEntity(self::entity()))
            ->firstOrFail();
    }

    public static function assertOwnedBy(Model $resource, PlantationEntity $entity): void
    {
        if ($resource instanceof WorkAttendance) {
            $resource->loadMissing('activity');

            if ($resource->activity === null
                || (int) $resource->activity->plantation_entity_id !== (int) $entity->id) {
                abort(404);
            }

            return;
        }

        if ($resource instanceof BudgetAllocationItem) {
            $resource->loadMissing('allocation');

            if ($resource->allocation === null
                || (int) $resource->allocation->plantation_entity_id !== (int) $entity->id) {
                abort(404);
            }

            return;
        }

        if ($resource instanceof BudgetRealization) {
            $resource->loadMissing('item.allocation');

            if ($resource->item?->allocation === null
                || (int) $resource->item->allocation->plantation_entity_id !== (int) $entity->id) {
                abort(404);
            }

            return;
        }

        if ($resource instanceof HarvestSalePayment) {
            $resource->loadMissing('sale');

            if ($resource->sale === null
                || (int) $resource->sale->plantation_entity_id !== (int) $entity->id) {
                abort(404);
            }

            return;
        }

        if ($resource instanceof PlantationBlock) {
            $resource->loadMissing('plantation');

            if ($resource->plantation === null || (int) $resource->plantation->plantation_entity_id !== (int) $entity->id) {
                abort(404);
            }

            return;
        }

        if (! isset($resource->plantation_entity_id) || (int) $resource->plantation_entity_id !== (int) $entity->id) {
            abort(404);
        }
    }

    private static function entity(): PlantationEntity
    {
        $entity = request()->route('plantationEntity');

        if (! $entity instanceof PlantationEntity) {
            abort(404);
        }

        return $entity;
    }
}
