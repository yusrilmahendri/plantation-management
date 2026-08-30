<?php

namespace App\Enums;

enum BudgetItemCategory: string
{
    case WAGES = 'WAGES';
    case FERTILIZER = 'FERTILIZER';
    case FUEL = 'FUEL';
    case HERBICIDE = 'HERBICIDE';
    case EQUIPMENT = 'EQUIPMENT';
    case RESERVE = 'RESERVE';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::WAGES => 'Upah',
            self::FERTILIZER => 'Pupuk',
            self::FUEL => 'BBM',
            self::HERBICIDE => 'Racun',
            self::EQUIPMENT => 'Peralatan',
            self::RESERVE => 'Cadangan',
            self::OTHER => 'Lainnya',
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
