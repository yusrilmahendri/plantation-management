<?php

namespace App\Enums;

enum PayrollRateType: string
{
    case DAILY = 'DAILY';
    case FIXED = 'FIXED';
    case UNIT = 'UNIT';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Harian',
            self::FIXED => 'Tetap',
            self::UNIT => 'Per unit',
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
