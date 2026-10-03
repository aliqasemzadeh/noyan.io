<?php

namespace App\Enums\Catalog;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case ReturnIn = 'return_in';
    case ReturnOut = 'return_out';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Opening => __('general.stock_movement_opening'),
            self::Purchase => __('general.stock_movement_purchase'),
            self::Sale => __('general.stock_movement_sale'),
            self::ReturnIn => __('general.stock_movement_return_in'),
            self::ReturnOut => __('general.stock_movement_return_out'),
            self::Adjustment => __('general.stock_movement_adjustment'),
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
