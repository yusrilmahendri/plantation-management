<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

class UpsertBudgetAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'budget_public_id' => ['sometimes', 'string', 'max:64'],
            'finance_entity_public_id' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
