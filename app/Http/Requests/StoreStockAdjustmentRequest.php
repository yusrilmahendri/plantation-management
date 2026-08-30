<?php

namespace App\Http\Requests;

use App\Enums\StockMovementType;
use App\Models\PlantationEntity;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends PlantationFormRequest
{
    public function rules(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        return [
            'inventory_item_public_id' => [
                'required',
                'string',
                Rule::exists('inventory_items', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'movement_type' => ['required', Rule::in([
                StockMovementType::ADJUSTMENT_IN->value,
                StockMovementType::ADJUSTMENT_OUT->value,
            ])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'movement_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{inventory_item_id: int, movement_type: string, quantity: string, movement_date: string, reason: string}
     */
    public function payload(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        $itemId = \App\Models\InventoryItem::query()
            ->forEntity($entity)
            ->where('public_id', $this->validated('inventory_item_public_id'))
            ->value('id');

        return [
            'inventory_item_id' => (int) $itemId,
            'movement_type' => (string) $this->validated('movement_type'),
            'quantity' => (string) $this->validated('quantity'),
            'movement_date' => (string) $this->validated('movement_date'),
            'reason' => (string) $this->validated('reason'),
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'inventory_item_public_id' => 'barang',
            'movement_type' => 'jenis penyesuaian',
            'quantity' => 'kuantitas',
            'movement_date' => 'tanggal',
            'reason' => 'alasan',
        ]);
    }
}
