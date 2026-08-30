<?php

namespace App\Http\Requests\Internal;

use App\Models\PlantationEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlantationEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('plantation_entities', 'slug')->ignore($entity->id),
            ],
            'finance_entity_public_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
