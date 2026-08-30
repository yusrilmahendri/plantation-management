<?php

namespace App\Support;

final class Quantity
{
    public const SCALE = 3;

    public static function normalize(mixed $value, int $scale = self::SCALE): string
    {
        if ($value === null || $value === '') {
            return bcadd('0', '0', $scale);
        }

        if (is_string($value) && is_numeric($value)) {
            return bcadd($value, '0', $scale);
        }

        return bcadd(number_format((float) $value, $scale, '.', ''), '0', $scale);
    }

    public static function add(mixed $left, mixed $right, int $scale = self::SCALE): string
    {
        return bcadd(self::normalize($left, $scale), self::normalize($right, $scale), $scale);
    }

    public static function sub(mixed $left, mixed $right, int $scale = self::SCALE): string
    {
        return bcsub(self::normalize($left, $scale), self::normalize($right, $scale), $scale);
    }

    public static function cmp(mixed $left, mixed $right, int $scale = self::SCALE): int
    {
        return bccomp(self::normalize($left, $scale), self::normalize($right, $scale), $scale);
    }

    public static function isPositive(mixed $value, int $scale = self::SCALE): bool
    {
        return self::cmp($value, '0', $scale) === 1;
    }

    public static function format(mixed $value, int $scale = self::SCALE): string
    {
        return rtrim(rtrim(self::normalize($value, $scale), '0'), '.') ?: '0';
    }
}
