<?php

namespace App\Enums;

enum IntegrationOutboxStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
