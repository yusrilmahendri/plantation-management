<?php

namespace App\Http\Requests;

use App\Enums\InventoryCategory;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends PlantationFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->prepareIsActive();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(InventoryCategory::class)],
            'unit' => ['required', 'string', 'max:50'],
            'sku' => ['nullable', 'string', 'max:100'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
