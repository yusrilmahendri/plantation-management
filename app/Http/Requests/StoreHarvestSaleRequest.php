<?php

namespace App\Http\Requests;

use App\Models\Buyer;
use App\Models\Harvest;
use App\Models\PlantationEntity;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHarvestSaleRequest extends PlantationFormRequest
{
    public function rules(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        return [
            'buyer_public_id' => [
                'required',
                'string',
                Rule::exists('buyers', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'sale_date' => ['required', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.harvest_public_id' => [
                'required',
                'string',
                Rule::exists('harvests', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || blank($this->input('buyer_public_id'))) {
                return;
            }

            /** @var PlantationEntity $entity */
            $entity = $this->route('plantationEntity');
            $buyer = Buyer::query()
                ->forEntity($entity)
                ->where('public_id', $this->input('buyer_public_id'))
                ->first();

            if ($buyer === null || ! $buyer->is_active) {
                $validator->errors()->add('buyer_public_id', 'Pembeli tidak aktif atau tidak valid.');
            }
        });
    }

    /**
     * @return array{
     *     buyer_id: int,
     *     sale_date: string,
     *     invoice_number: string|null,
     *     description: string|null,
     *     adjustment_amount: string,
     *     items: list<array{harvest_id: int, quantity: string, unit_price: string}>
     * }
     */
    public function payload(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        $buyerId = Buyer::query()
            ->forEntity($entity)
            ->where('public_id', $this->validated('buyer_public_id'))
            ->value('id');

        $items = [];
        foreach ($this->validated('items') as $line) {
            $harvestId = Harvest::query()
                ->forEntity($entity)
                ->where('public_id', $line['harvest_public_id'])
                ->value('id');

            $items[] = [
                'harvest_id' => (int) $harvestId,
                'quantity' => (string) $line['quantity'],
                'unit_price' => (string) $line['unit_price'],
            ];
        }

        return [
            'buyer_id' => (int) $buyerId,
            'sale_date' => (string) $this->validated('sale_date'),
            'invoice_number' => $this->validated('invoice_number'),
            'description' => $this->validated('description'),
            'adjustment_amount' => (string) ($this->validated('adjustment_amount') ?? '0'),
            'items' => $items,
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'buyer_public_id' => 'pembeli',
            'sale_date' => 'tanggal penjualan',
            'invoice_number' => 'nomor invoice',
            'adjustment_amount' => 'penyesuaian',
            'items' => 'hasil panen',
            'items.*.harvest_public_id' => 'panen',
            'items.*.quantity' => 'kuantitas',
            'items.*.unit_price' => 'harga satuan',
        ]);
    }
}
