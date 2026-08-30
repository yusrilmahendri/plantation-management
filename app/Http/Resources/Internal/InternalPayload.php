<?php

namespace App\Http\Resources\Internal;

use App\Models\FinanceBudgetAllocation;
use App\Models\HarvestSale;
use App\Models\HarvestSalePayment;
use App\Models\PlantationAccessToken;
use App\Models\PlantationEntity;

class InternalPayload
{
    public static function entity(PlantationEntity $entity): array
    {
        return [
            'public_id' => $entity->public_id,
            'name' => $entity->name,
            'slug' => $entity->slug,
            'finance_entity_public_id' => $entity->finance_entity_public_id,
            'description' => $entity->description,
            'is_active' => $entity->is_active,
            'created_at' => $entity->created_at?->toIso8601String(),
            'updated_at' => $entity->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Metadata only. Never includes token_hash or plaintext.
     *
     * @return array{id: int, label: string|null, is_active: bool, expires_at: string|null, last_used_at: string|null, created_at: string|null}
     */
    public static function accessLinkMetadata(PlantationAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'label' => $token->label,
            'is_active' => $token->is_active,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }

    public static function accessLink(PlantationAccessToken $token, ?string $plainToken = null): array
    {
        $payload = self::accessLinkMetadata($token);
        $payload['updated_at'] = $token->updated_at?->toIso8601String();

        if ($plainToken !== null) {
            $payload['token'] = $plainToken;
            $payload['access_url'] = url('/access/'.$plainToken);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function budgetAllocation(FinanceBudgetAllocation $allocation): array
    {
        return [
            'public_id' => $allocation->public_id,
            'finance_budget_public_id' => $allocation->finance_budget_public_id,
            'name' => $allocation->name,
            'period_start' => $allocation->period_start?->toDateString(),
            'period_end' => $allocation->period_end?->toDateString(),
            'allocated_amount' => (string) $allocation->allocated_amount,
            'status' => $allocation->status,
            'synced_at' => $allocation->synced_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function harvestSale(HarvestSale $sale): array
    {
        $sale->loadMissing(['buyer', 'payments']);

        return [
            'public_id' => $sale->public_id,
            'buyer_name' => $sale->buyer?->name,
            'sale_date' => $sale->sale_date?->toDateString(),
            'invoice_number' => $sale->invoice_number,
            'description' => $sale->description,
            'total_amount' => (string) $sale->total_amount,
            'status' => $sale->status?->value,
            'payment_status' => $sale->payment_status?->value,
            'payments' => $sale->payments
                ->sortBy('id')
                ->values()
                ->map(fn (HarvestSalePayment $payment) => [
                    'public_id' => $payment->public_id,
                    'amount' => (string) $payment->amount,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'payment_method' => $payment->payment_method?->value,
                    'reference_number' => $payment->reference_number,
                    'notes' => $payment->notes,
                    'status' => $payment->status?->value,
                ])
                ->all(),
        ];
    }
}
