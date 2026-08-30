<?php

namespace App\Enums;

enum StockSourceType: string
{
    case INVENTORY_PURCHASE = 'INVENTORY_PURCHASE';
    case MATERIAL_USAGE = 'MATERIAL_USAGE';
    case FERTILIZER_APPLICATION = 'FERTILIZER_APPLICATION';
    case STOCK_ADJUSTMENT = 'STOCK_ADJUSTMENT';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
