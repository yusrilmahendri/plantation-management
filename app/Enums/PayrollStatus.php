<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case DRAFT = 'DRAFT';
    case POSTED = 'POSTED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::POSTED => 'Diposting',
            self::CANCELLED => 'Dibatalkan',
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
