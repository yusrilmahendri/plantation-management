<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class StoreHarvestSalePaymentRequest extends PlantationFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'amount' => 'jumlah',
            'payment_date' => 'tanggal pembayaran',
            'payment_method' => 'metode pembayaran',
            'reference_number' => 'nomor referensi',
        ]);
    }
}
