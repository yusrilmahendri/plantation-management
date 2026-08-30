<?php

namespace App\Enums;

enum SalePaymentStatus: string
{
    case UNPAID = 'UNPAID';
    case PARTIAL = 'PARTIAL';
    case PAID = 'PAID';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Dibayar',
            self::PARTIAL => 'Sebagian',
            self::PAID => 'Lunas',
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
