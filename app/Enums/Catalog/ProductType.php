<?php

namespace App\Enums\Catalog;

enum ProductType: string
{
    case Goods = 'goods';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Goods => __('general.product_type_goods'),
            self::Service => __('general.product_type_service'),
        };
    }

    public function tracksInventoryByDefault(): bool
    {
        return $this === self::Goods;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
