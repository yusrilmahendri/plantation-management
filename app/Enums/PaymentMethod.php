<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case BANK_TRANSFER = 'BANK_TRANSFER';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Tunai',
            self::BANK_TRANSFER => 'Transfer bank',
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
