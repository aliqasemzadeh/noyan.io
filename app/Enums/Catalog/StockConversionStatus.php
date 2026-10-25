<?php

namespace App\Enums\Catalog;

enum StockConversionStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('general.stock_conversion_status_draft'),
            self::Posted => __('general.stock_conversion_status_posted'),
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
