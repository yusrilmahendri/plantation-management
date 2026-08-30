<?php

namespace App\Http\Requests;

use App\Models\PlantationEntity;
use Illuminate\Validation\Rule;

class StoreWorkTypeRequest extends PlantationFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIsActive();
    }

    public function rules(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('work_types', 'name')->where('plantation_entity_id', $entity->id),
            ],
            'description' => ['nullable', 'string'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
