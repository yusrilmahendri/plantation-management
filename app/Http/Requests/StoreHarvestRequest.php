<?php

namespace App\Http\Requests;

use App\Enums\Commodity;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\WorkActivity;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHarvestRequest extends PlantationFormRequest
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
            'work_activity_public_id' => [
                'nullable',
                'string',
                Rule::exists('work_activities', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'harvest_date' => ['required', 'date'],
            'commodity' => ['required', Rule::enum(Commodity::class)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:50'],
            'bunch_count' => ['nullable', 'integer', 'min:0'],
            'quality_grade' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
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

            if (filled($this->input('plantation_block_public_id'))) {
                $block = PlantationBlock::query()
                    ->where('public_id', $this->input('plantation_block_public_id'))
                    ->first();

                if ($plantation === null || $block === null || (int) $block->plantation_id !== (int) $plantation->id) {
                    $validator->errors()->add('plantation_block_public_id', 'Blok harus milik kebun yang dipilih.');
                }
            }
        });
    }

    /**
     * @return array{
     *     plantation_id: int,
     *     plantation_block_id: int|null,
     *     work_activity_id: int|null,
     *     harvest_date: string,
     *     commodity: string,
     *     quantity: string,
     *     unit: string,
     *     bunch_count: int|null,
     *     quality_grade: string|null,
     *     notes: string|null
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

        $activityId = null;
        if (filled($this->validated('work_activity_public_id'))) {
            $activityId = WorkActivity::query()
                ->forEntity($entity)
                ->where('public_id', $this->validated('work_activity_public_id'))
                ->value('id');
        }

        return [
            'plantation_id' => (int) $plantation->id,
            'plantation_block_id' => $blockId ? (int) $blockId : null,
            'work_activity_id' => $activityId ? (int) $activityId : null,
            'harvest_date' => (string) $this->validated('harvest_date'),
            'commodity' => (string) $this->validated('commodity'),
            'quantity' => (string) $this->validated('quantity'),
            'unit' => (string) $this->validated('unit'),
            'bunch_count' => isset($this->validated()['bunch_count']) ? (int) $this->validated('bunch_count') : null,
            'quality_grade' => $this->validated('quality_grade'),
            'notes' => $this->validated('notes'),
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'plantation_public_id' => 'kebun',
            'plantation_block_public_id' => 'blok',
            'work_activity_public_id' => 'aktivitas kerja',
            'harvest_date' => 'tanggal panen',
            'commodity' => 'komoditas',
            'quantity' => 'kuantitas',
            'unit' => 'satuan',
            'bunch_count' => 'jumlah tandan',
            'quality_grade' => 'mutu',
        ]);
    }
}
