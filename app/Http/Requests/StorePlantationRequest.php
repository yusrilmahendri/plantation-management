<?php

namespace App\Http\Requests;

class StorePlantationRequest extends PlantationFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIsActive();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'total_area' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
