<?php

namespace App\Http\Requests;

use App\Models\BudgetAllocationItem;
use App\Models\InventoryItem;
use App\Models\PlantationEntity;
use App\Models\Supplier;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryPurchaseRequest extends PlantationFormRequest
{
    public function rules(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        return [
            'supplier_public_id' => [
                'nullable',
                'string',
                Rule::exists('suppliers', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'purchase_date' => ['required', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'budget_allocation_item_public_id' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_public_id' => [
                'required',
                'string',
                Rule::exists('inventory_items', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var PlantationEntity $entity */
            $entity = $this->route('plantationEntity');

            if (filled($this->input('budget_allocation_item_public_id'))) {
                $item = BudgetAllocationItem::query()
                    ->where('public_id', $this->input('budget_allocation_item_public_id'))
                    ->whereHas('allocation', fn ($query) => $query->forEntity($entity))
                    ->first();

                if ($item === null) {
                    $validator->errors()->add('budget_allocation_item_public_id', 'Item anggaran tidak valid.');
                }
            }
        });
    }

    /**
     * @return array{
     *     supplier_id: int|null,
     *     purchase_date: string,
     *     invoice_number: string|null,
     *     description: string|null,
     *     adjustment_amount: string,
     *     budget_allocation_item_id: int|null,
     *     items: list<array{inventory_item_id: int, quantity: string, unit_cost: string}>
     * }
     */
    public function payload(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        $supplierId = null;
        if (filled($this->validated('supplier_public_id'))) {
            $supplierId = Supplier::query()
                ->forEntity($entity)
                ->where('public_id', $this->validated('supplier_public_id'))
                ->value('id');
        }

        $budgetItemId = null;
        if (filled($this->validated('budget_allocation_item_public_id'))) {
            $budgetItemId = BudgetAllocationItem::query()
                ->where('public_id', $this->validated('budget_allocation_item_public_id'))
                ->whereHas('allocation', fn ($query) => $query->where('plantation_entity_id', $entity->id))
                ->value('id');
        }

        $items = [];
        foreach ($this->validated('items') as $line) {
            $itemId = InventoryItem::query()
                ->forEntity($entity)
                ->where('public_id', $line['inventory_item_public_id'])
                ->value('id');

            $items[] = [
                'inventory_item_id' => (int) $itemId,
                'quantity' => (string) $line['quantity'],
                'unit_cost' => (string) $line['unit_cost'],
            ];
        }

        return [
            'supplier_id' => $supplierId ? (int) $supplierId : null,
            'purchase_date' => (string) $this->validated('purchase_date'),
            'invoice_number' => $this->validated('invoice_number'),
            'description' => $this->validated('description'),
            'adjustment_amount' => (string) ($this->validated('adjustment_amount') ?? '0'),
            'budget_allocation_item_id' => $budgetItemId ? (int) $budgetItemId : null,
            'items' => $items,
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'supplier_public_id' => 'supplier',
            'purchase_date' => 'tanggal pembelian',
            'invoice_number' => 'nomor invoice',
            'adjustment_amount' => 'penyesuaian',
            'budget_allocation_item_public_id' => 'item anggaran',
            'items' => 'item pembelian',
            'items.*.inventory_item_public_id' => 'barang',
            'items.*.quantity' => 'kuantitas',
            'items.*.unit_cost' => 'harga satuan',
        ]);
    }
}
