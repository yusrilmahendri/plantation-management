<?php

namespace Database\Factories;

use App\Models\FinanceBudgetAllocation;
use App\Models\PlantationEntity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinanceBudgetAllocation>
 */
class FinanceBudgetAllocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plantation_entity_id' => PlantationEntity::factory(),
            'finance_budget_public_id' => (string) Str::ulid(),
            'name' => 'Anggaran Operasional '.fake()->monthName(),
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'allocated_amount' => 50_000_000,
            'status' => 'ACTIVE',
            'synced_at' => now(),
        ];
    }
}
