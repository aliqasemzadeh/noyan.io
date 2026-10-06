<?php

namespace App\Support;

class Money
{
    public static function normalize(?string $amount): string
    {
        if ($amount === null) {
            return '';
        }

        $amount = trim($amount);

        if ($amount === '') {
            return '';
        }

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $amount = str_replace($persian, $english, $amount);
        $amount = str_replace($arabic, $english, $amount);
        $amount = str_replace([',', '٬', ' '], '', $amount);

        return $amount;
    }

    public static function format(?string $amount, int $decimalPlaces = 0): string
    {
        $normalized = self::normalize($amount);

        if ($normalized === '' || ! is_numeric($normalized)) {
            $normalized = '0';
        }

        $decimalPlaces = max(0, $decimalPlaces);

        if (function_exists('bcadd')) {
            $normalized = bcadd($normalized, '0', $decimalPlaces);
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');

        if (str_contains($normalized, '.')) {
            [$integer, $fraction] = explode('.', $normalized, 2);
        } else {
            $integer = $normalized;
            $fraction = $decimalPlaces > 0 ? str_repeat('0', $decimalPlaces) : '';
        }

        $integer = ltrim($integer, '0') ?: '0';
        $formatted = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integer) ?? $integer;

        if ($decimalPlaces > 0) {
            $formatted .= '.'.str_pad(substr($fraction, 0, $decimalPlaces), $decimalPlaces, '0');
        }

        return ($negative ? '-' : '').$formatted;
    }
}
