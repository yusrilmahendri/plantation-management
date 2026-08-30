<?php

namespace App\Support;

final class Money
{
    public static function normalize(mixed $value): string
    {
        if (is_string($value) && preg_match('/^-?\d+\.\d{2}$/', $value) === 1) {
            return $value;
        }

        return number_format((float) $value, 2, '.', '');
    }

    public static function add(mixed $left, mixed $right): string
    {
        return bcadd(self::normalize($left), self::normalize($right), 2);
    }

    public static function sub(mixed $left, mixed $right): string
    {
        return bcsub(self::normalize($left), self::normalize($right), 2);
    }

    public static function mul(mixed $left, mixed $right): string
    {
        return bcmul(self::normalize($left), self::normalize($right), 2);
    }

    public static function lineTotal(mixed $quantity, mixed $unitCost): string
    {
        return bcadd('0', bcmul(Quantity::normalize($quantity), self::normalize($unitCost), 4), 2);
    }

    public static function cmp(mixed $left, mixed $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), 2);
    }

    public static function format(mixed $amount): string
    {
        $number = (float) self::normalize($amount);

        return ($number < 0 ? '-' : '').'Rp '.number_format(abs($number), 0, ',', '.');
    }
}
