<?php

namespace App\Http\Requests;

class StoreBudgetRealizationRequest extends PlantationFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'realization_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'allocated_amount' => 'jumlah alokasi',
            'amount' => 'jumlah',
            'realization_date' => 'tanggal realisasi',
            'period_start' => 'awal periode',
            'period_end' => 'akhir periode',
        ]);
    }
}
