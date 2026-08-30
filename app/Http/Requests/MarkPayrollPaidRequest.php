<?php

namespace App\Http\Requests;

class MarkPayrollPaidRequest extends PlantationFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paid_at' => ['required', 'date'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'paid_at' => 'waktu pembayaran',
            'payment_notes' => 'catatan pembayaran',
        ]);
    }
}
