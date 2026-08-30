<?php

namespace App\Enums;

enum InventoryDocumentStatus: string
{
    case DRAFT = 'DRAFT';
    case POSTED = 'POSTED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::POSTED => 'Posted',
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
