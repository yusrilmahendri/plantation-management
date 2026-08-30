<?php

namespace App\Http\Requests;

use App\Enums\WorkActivityStatus;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\WorkType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkActivityRequest extends PlantationFormRequest
{
    /**
     * @return array<string, mixed>
     */
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
            'work_type_public_id' => [
                'required',
                'string',
                Rule::exists('work_types', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'activity_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(WorkActivityStatus::values())],
            'started_at' => ['nullable', 'date'],
            'finished_at' => ['nullable', 'date', 'after_or_equal:started_at'],
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
     * @return array{plantation_id: int, plantation_block_id: int|null, work_type_id: int, activity_date: string, title: string, description: string|null, status: string, started_at: string|null, finished_at: string|null}
     */
    public function payload(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        $plantation = Plantation::query()
            ->forEntity($entity)
            ->where('public_id', $this->validated('plantation_public_id'))
            ->firstOrFail();

        $workType = WorkType::query()
            ->forEntity($entity)
            ->where('public_id', $this->validated('work_type_public_id'))
            ->firstOrFail();

        $blockId = null;
        if (filled($this->validated('plantation_block_public_id'))) {
            $blockId = PlantationBlock::query()
                ->where('plantation_id', $plantation->id)
                ->where('public_id', $this->validated('plantation_block_public_id'))
                ->value('id');
        }

        return [
            'plantation_id' => (int) $plantation->id,
            'plantation_block_id' => $blockId ? (int) $blockId : null,
            'work_type_id' => (int) $workType->id,
            'activity_date' => (string) $this->validated('activity_date'),
            'title' => (string) $this->validated('title'),
            'description' => $this->validated('description'),
            'status' => (string) ($this->validated('status') ?? WorkActivityStatus::DRAFT->value),
            'started_at' => $this->validated('started_at'),
            'finished_at' => $this->validated('finished_at'),
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'plantation_public_id' => 'kebun',
            'plantation_block_public_id' => 'blok',
            'work_type_public_id' => 'jenis pekerjaan',
            'activity_date' => 'tanggal kegiatan',
            'title' => 'judul',
            'started_at' => 'waktu mulai',
            'finished_at' => 'waktu selesai',
            'status' => 'status',
        ]);
    }
}
