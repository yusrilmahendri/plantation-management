<?php

namespace App\Http\Requests;

use App\Models\Plantation;
use App\Models\PlantationEntity;
use Illuminate\Validation\Rule;

class StorePlantationBlockRequest extends PlantationFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIsActive();
    }

    public function rules(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        $plantation = Plantation::query()
            ->forEntity($entity)
            ->where('public_id', $this->input('plantation_public_id'))
            ->first();

        return [
            'plantation_public_id' => [
                'required',
                'string',
                Rule::exists('plantations', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('plantation_blocks', 'code')->where(
                    fn ($query) => $query->where('plantation_id', $plantation?->id)
                ),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'crop_type' => ['nullable', 'string', 'max:255'],
            'planting_year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 5)],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function plantationId(): int
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        return (int) Plantation::query()
            ->forEntity($entity)
            ->where('public_id', $this->validated('plantation_public_id'))
            ->firstOrFail()
            ->id;
    }
}
