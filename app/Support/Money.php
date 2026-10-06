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
}
