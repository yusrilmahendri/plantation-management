<?php

namespace App\Http\Requests;

use App\Enums\PayrollRateType;
use Illuminate\Validation\Rule;

class UpdatePayrollRequest extends PlantationFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rate_type' => ['required', Rule::in(PayrollRateType::values())],
            'rate_amount' => ['required', 'numeric', 'min:0.01'],
            'work_quantity' => ['nullable', 'numeric', 'min:0'],
            'adjustment_amount' => ['nullable', 'numeric'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'rate_type' => 'jenis tarif',
            'rate_amount' => 'tarif',
            'work_quantity' => 'kuantitas',
            'adjustment_amount' => 'penyesuaian',
        ]);
    }
}
