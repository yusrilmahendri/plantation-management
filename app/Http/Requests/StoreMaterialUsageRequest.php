<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMaterialUsageRequest extends PlantationFormRequest
{
    public function rules(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        return [
            'plantation_public_id' => [
                'required',
                'string',
                Rule::exists('plantations', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'plantation_block_public_id' => ['nullable', 'string'],
            'usage_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_public_id' => [
                'required',
                'string',
                Rule::exists('inventory_items', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || blank($this->input('plantation_block_public_id'))) {
                return;
            }

            /** @var PlantationEntity $entity */
            $entity = $this->route('plantationEntity');
            $plantation = Plantation::query()
                ->forEntity($entity)
                ->where('public_id', $this->input('plantation_public_id'))
                ->first();

            $block = PlantationBlock::query()
                ->where('public_id', $this->input('plantation_block_public_id'))
                ->first();

            if ($plantation === null || $block === null || (int) $block->plantation_id !== (int) $plantation->id) {
                $validator->errors()->add('plantation_block_public_id', 'Blok harus milik kebun yang dipilih.');
            }
        });
    }

    /**
     * @return array{
     *     plantation_id: int,
     *     plantation_block_id: int|null,
     *     usage_date: string,
     *     description: string|null,
     *     items: list<array{inventory_item_id: int, quantity: string, notes: string|null}>
     * }
     */
    public function payload(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        $plantation = Plantation::query()
            ->forEntity($entity)
            ->where('public_id', $this->validated('plantation_public_id'))
            ->firstOrFail();

        $blockId = null;
        if (filled($this->validated('plantation_block_public_id'))) {
            $blockId = PlantationBlock::query()
                ->where('plantation_id', $plantation->id)
                ->where('public_id', $this->validated('plantation_block_public_id'))
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
                'notes' => $line['notes'] ?? null,
            ];
        }

        return [
            'plantation_id' => (int) $plantation->id,
            'plantation_block_id' => $blockId ? (int) $blockId : null,
            'usage_date' => (string) $this->validated('usage_date'),
            'description' => $this->validated('description'),
            'items' => $items,
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'plantation_public_id' => 'kebun',
            'plantation_block_public_id' => 'blok',
            'usage_date' => 'tanggal pemakaian',
            'items' => 'item pemakaian',
            'items.*.inventory_item_public_id' => 'barang',
            'items.*.quantity' => 'kuantitas',
        ]);
    }
}
