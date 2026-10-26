<?php

namespace App\Support\Warehouse;

class Quantity
{
    public static function format(string|int|float|null $amount): string
    {
        $value = trim((string) ($amount ?? '0'));

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        $formatted = number_format((float) $value, 4, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        if ($trimmed === '' || $trimmed === '-') {
            return '0';
        }

        return $trimmed;
    }

    public static function normalize(string|int|float|null $amount): string
    {
        $value = str_replace([',', ' '], '', trim((string) ($amount ?? '0')));

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bcadd($value, '0', 18);
    }
}
