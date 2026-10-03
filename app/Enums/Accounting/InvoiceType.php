<?php

namespace App\Enums\Accounting;

enum InvoiceType: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Sale => __('general.invoice_type_sale'),
            self::Purchase => __('general.invoice_type_purchase'),
            self::Return => __('general.invoice_type_return'),
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Sale => 'SL',
            self::Purchase => 'PR',
            self::Return => 'RT',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Sale => 'teal',
            self::Purchase => 'amber',
            self::Return => 'rose',
        };
    }

    /**
     * @return list<self>
     */
    public static function creatable(): array
    {
        return [self::Sale, self::Purchase];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
