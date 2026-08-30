<?php

namespace App\Enums;

enum Commodity: string
{
    case PALM_OIL_FFB = 'PALM_OIL_FFB';
    case PEPPER = 'PEPPER';
    case RUBBER = 'RUBBER';
    case DURIAN = 'DURIAN';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::PALM_OIL_FFB => 'TBS Sawit',
            self::PEPPER => 'Lada',
            self::RUBBER => 'Karet',
            self::DURIAN => 'Durian',
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
