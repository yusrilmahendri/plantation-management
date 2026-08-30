<?php

namespace App\Enums;

enum StockMovementType: string
{
    case PURCHASE_IN = 'PURCHASE_IN';
    case USAGE_OUT = 'USAGE_OUT';
    case ADJUSTMENT_IN = 'ADJUSTMENT_IN';
    case ADJUSTMENT_OUT = 'ADJUSTMENT_OUT';
    case RETURN_IN = 'RETURN_IN';
    case RETURN_OUT = 'RETURN_OUT';

    public function isInbound(): bool
    {
        return match ($this) {
            self::PURCHASE_IN, self::ADJUSTMENT_IN, self::RETURN_IN => true,
            self::USAGE_OUT, self::ADJUSTMENT_OUT, self::RETURN_OUT => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function inboundValues(): array
    {
        return [
            self::PURCHASE_IN->value,
            self::ADJUSTMENT_IN->value,
            self::RETURN_IN->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function outboundValues(): array
    {
        return [
            self::USAGE_OUT->value,
            self::ADJUSTMENT_OUT->value,
            self::RETURN_OUT->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
