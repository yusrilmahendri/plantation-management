<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'UNPAID';
    case PAID = 'PAID';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum dibayar',
            self::PAID => 'Sudah dibayar',
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
