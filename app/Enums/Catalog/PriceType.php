<?php

namespace App\Enums\Catalog;

enum PriceType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => __('general.price_type_purchase'),
            self::Sale => __('general.price_type_sale'),
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
