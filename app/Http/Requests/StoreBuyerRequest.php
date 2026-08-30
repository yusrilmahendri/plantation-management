<?php

namespace App\Http\Requests;

class StoreBuyerRequest extends PlantationFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIsActive();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'contact_person' => 'kontak',
        ]);
    }
}
