<?php

namespace App\Enums;

enum RealizationSourceType: string
{
    case MANUAL = 'MANUAL';
    case WORKER_PAYROLL = 'WORKER_PAYROLL';
    case INVENTORY_PURCHASE = 'INVENTORY_PURCHASE';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
