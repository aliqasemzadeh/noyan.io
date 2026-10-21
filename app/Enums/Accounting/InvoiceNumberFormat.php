<?php

namespace App\Enums\Accounting;

enum InvoiceNumberFormat: string
{
    case Year = 'year';
    case YearMonth = 'year_month';

    public function label(): string
    {
        return match ($this) {
            self::Year => __('general.invoice_number_format_year'),
            self::YearMonth => __('general.invoice_number_format_year_month'),
        };
    }

    public function periodPattern(\Morilog\Jalali\Jalalian $jalali): string
    {
        return match ($this) {
            self::Year => $jalali->format('Y'),
            self::YearMonth => $jalali->format('Y-m'),
        };
    }
}
