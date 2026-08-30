<?php

namespace App\Http\Requests;

class PostPayrollRequest extends PlantationFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'budget_allocation_item_public_id' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'budget_allocation_item_public_id' => 'item anggaran upah',
        ]);
    }
}
