<?php

namespace App\Enums\Catalog;

enum StockCountResolution: string
{
    case Pending = 'pending';
    case Adjusted = 'adjusted';
    case Accepted = 'accepted';

    public function label(): string
    {
        return __('general.stock_count_resolution_'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
