<?php

namespace App\Enums;

enum InventoryCategory: string
{
    case Fertilizer = 'FERTILIZER';
    case Herbicide = 'HERBICIDE';
    case Fuel = 'FUEL';
    case Tool = 'TOOL';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Fertilizer => 'Pupuk',
            self::Herbicide => 'Herbisida',
            self::Fuel => 'Bahan bakar',
            self::Tool => 'Alat',
            self::Other => 'Lainnya',
        };
    }

    public function budgetCategory(): BudgetItemCategory
    {
        return match ($this) {
            self::Fertilizer => BudgetItemCategory::FERTILIZER,
            self::Herbicide => BudgetItemCategory::HERBICIDE,
            self::Fuel => BudgetItemCategory::FUEL,
            self::Tool => BudgetItemCategory::EQUIPMENT,
            self::Other => BudgetItemCategory::OTHER,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
