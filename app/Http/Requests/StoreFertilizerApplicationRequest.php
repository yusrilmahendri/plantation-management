<?php

namespace App\Http\Requests;

use App\Enums\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\WorkActivity;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFertilizerApplicationRequest extends PlantationFormRequest
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
            'plantation_block_public_id' => ['required', 'string'],
            'application_date' => ['required', 'date'],
            'work_activity_public_id' => [
                'nullable',
                'string',
                Rule::exists('work_activities', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_public_id' => [
                'required',
                'string',
                Rule::exists('inventory_items', 'public_id')->where(fn ($query) => $query
                    ->where('plantation_entity_id', $entity->id)
                    ->where('category', InventoryCategory::Fertilizer->value)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.dosage_per_plant' => ['nullable', 'numeric', 'min:0'],
            'items.*.plant_count' => ['nullable', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
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
     *     plantation_block_id: int,
     *     application_date: string,
     *     work_activity_id: int|null,
     *     notes: string|null,
     *     items: list<array{inventory_item_id: int, quantity: string, dosage_per_plant: string|null, plant_count: int|null, notes: string|null}>
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

        $blockId = PlantationBlock::query()
            ->where('plantation_id', $plantation->id)
            ->where('public_id', $this->validated('plantation_block_public_id'))
            ->value('id');

        $activityId = null;
        if (filled($this->validated('work_activity_public_id'))) {
            $activityId = WorkActivity::query()
                ->forEntity($entity)
                ->where('public_id', $this->validated('work_activity_public_id'))
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
                'dosage_per_plant' => $line['dosage_per_plant'] ?? null,
                'plant_count' => isset($line['plant_count']) ? (int) $line['plant_count'] : null,
                'notes' => $line['notes'] ?? null,
            ];
        }

        return [
            'plantation_id' => (int) $plantation->id,
            'plantation_block_id' => (int) $blockId,
            'application_date' => (string) $this->validated('application_date'),
            'work_activity_id' => $activityId ? (int) $activityId : null,
            'notes' => $this->validated('notes'),
            'items' => $items,
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'plantation_public_id' => 'kebun',
            'plantation_block_public_id' => 'blok',
            'application_date' => 'tanggal pemupukan',
            'work_activity_public_id' => 'aktivitas kerja',
            'items' => 'item pupuk',
            'items.*.inventory_item_public_id' => 'pupuk',
            'items.*.quantity' => 'kuantitas',
            'items.*.dosage_per_plant' => 'dosis per tanaman',
            'items.*.plant_count' => 'jumlah tanaman',
        ]);
    }
}
