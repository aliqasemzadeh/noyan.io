<?php

namespace App\Enums\Catalog;

enum StockConversionItemDirection: string
{
    case Input = 'input';
    case Output = 'output';

    public function label(): string
    {
        return match ($this) {
            self::Input => __('general.stock_conversion_direction_input'),
            self::Output => __('general.stock_conversion_direction_output'),
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
