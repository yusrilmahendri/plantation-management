<?php

namespace App\Enums;

enum PaymentRecordStatus: string
{
    case ACTIVE = 'ACTIVE';
    case REVERSED = 'REVERSED';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::REVERSED => 'Dibatalkan',
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
