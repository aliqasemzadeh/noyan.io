<?php

namespace App\Enums\Business;

enum InvoiceOrientation: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';

    public function label(): string
    {
        return match ($this) {
            self::Portrait => __('general.invoice_orientation_portrait'),
            self::Landscape => __('general.invoice_orientation_landscape'),
        };
    }

    public function pageSize(): string
    {
        return match ($this) {
            self::Portrait => 'A4 portrait',
            self::Landscape => 'A4 landscape',
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
