<?php

namespace App\Enums\Catalog;

enum ProductUnit: string
{
    case Piece = 'piece';
    case Kilogram = 'kilogram';
    case Meter = 'meter';
    case Hour = 'hour';
    case Pack = 'pack';
    case Box = 'box';
    case Liter = 'liter';

    public function label(): string
    {
        return match ($this) {
            self::Piece => __('general.product_unit_piece'),
            self::Kilogram => __('general.product_unit_kilogram'),
            self::Meter => __('general.product_unit_meter'),
            self::Hour => __('general.product_unit_hour'),
            self::Pack => __('general.product_unit_pack'),
            self::Box => __('general.product_unit_box'),
            self::Liter => __('general.product_unit_liter'),
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
