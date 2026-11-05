<?php

namespace App\Support;

use App\Enums\Accounting\CreditDisplayStyle;

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

    /**
     * Compare a normalized amount to zero.
     *
     * @return int -1 when negative, 0 when zero, 1 when positive
     */
    public static function sign(?string $amount): int
    {
        $normalized = self::normalize($amount);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return 0;
        }

        if (function_exists('bccomp')) {
            return bccomp($normalized, '0', 18);
        }

        $value = (float) $normalized;

        return $value <=> 0.0;
    }

    public static function formatSigned(
        ?string $amount,
        int $decimalPlaces = 0,
        CreditDisplayStyle|string|null $creditStyle = null,
    ): string {
        $style = self::resolveCreditStyle($creditStyle);
        $sign = self::sign($amount);
        $absolute = self::format(self::absolute($amount), $decimalPlaces);

        if ($sign < 0) {
            return match ($style) {
                CreditDisplayStyle::Parentheses => '('.$absolute.')',
                CreditDisplayStyle::Minus => '-'.$absolute,
            };
        }

        return $absolute;
    }

    public static function colorClass(?string $amount): string
    {
        return match (self::sign($amount)) {
            1 => 'text-emerald-600 dark:text-emerald-400',
            -1 => 'text-rose-600 dark:text-rose-400',
            default => 'text-zinc-700 dark:text-zinc-300',
        };
    }

    public static function absolute(?string $amount): string
    {
        $normalized = self::normalize($amount);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return '0';
        }

        return ltrim($normalized, '+-') ?: '0';
    }

    public static function resolveCreditStyle(CreditDisplayStyle|string|null $style = null): CreditDisplayStyle
    {
        if ($style instanceof CreditDisplayStyle) {
            return $style;
        }

        if (is_string($style) && $style !== '') {
            return CreditDisplayStyle::tryFrom($style) ?? CreditDisplayStyle::Parentheses;
        }

        return CreditDisplayStyle::Parentheses;
    }
}
