<?php

namespace App\Http\Requests;

use App\Enums\PayrollRateType;
use Illuminate\Validation\Rule;

class GeneratePayrollRequest extends PlantationFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendance_public_ids' => ['required', 'array', 'min:1'],
            'attendance_public_ids.*' => ['required', 'string'],
            'rate_type' => ['required', Rule::in(PayrollRateType::values())],
            'rate_amount' => ['nullable', 'numeric', 'min:0'],
            'work_quantity' => ['nullable', 'numeric', 'min:0'],
            'adjustment_amount' => ['nullable', 'numeric'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'attendance_public_ids' => 'absensi',
            'rate_type' => 'jenis tarif',
            'rate_amount' => 'tarif',
            'work_quantity' => 'kuantitas',
            'adjustment_amount' => 'penyesuaian',
        ]);
    }
}
