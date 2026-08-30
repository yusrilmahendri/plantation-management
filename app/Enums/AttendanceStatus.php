<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case PRESENT = 'PRESENT';
    case ABSENT = 'ABSENT';
    case SICK = 'SICK';
    case PERMISSION = 'PERMISSION';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Hadir',
            self::ABSENT => 'Tidak hadir',
            self::SICK => 'Sakit',
            self::PERMISSION => 'Izin',
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
