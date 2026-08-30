<?php

namespace App\Http\Requests;

use App\Models\PlantationEntity;
use App\Models\WorkType;
use Illuminate\Validation\Rule;

class UpdateWorkTypeRequest extends PlantationFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIsActive();
    }

    public function rules(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');
        /** @var WorkType $workType */
        $workType = $this->route('workType');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('work_types', 'name')
                    ->where('plantation_entity_id', $entity->id)
                    ->ignore($workType->id),
            ],
            'description' => ['nullable', 'string'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
