<?php

namespace App\Http\Requests;

class ReverseHarvestSalePaymentRequest extends PlantationFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'reason' => 'alasan pembatalan',
        ]);
    }
}
