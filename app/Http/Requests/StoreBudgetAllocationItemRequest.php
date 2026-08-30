<?php

namespace App\Http\Requests;

use App\Enums\BudgetItemCategory;
use Illuminate\Validation\Rule;

class StoreBudgetAllocationItemRequest extends PlantationFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(BudgetItemCategory::values())],
            'name' => ['required', 'string', 'max:255'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
