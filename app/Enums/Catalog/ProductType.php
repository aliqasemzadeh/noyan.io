<?php

namespace App\Enums\Catalog;

enum ProductType: string
{
    case Goods = 'goods';
    case Digital = 'digital';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Goods => __('general.product_type_goods'),
            self::Digital => __('general.product_type_digital'),
            self::Service => __('general.product_type_service'),
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Goods => 1,
            self::Digital => 2,
            self::Service => 3,
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Goods => 'teal',
            self::Digital => 'sky',
            self::Service => 'violet',
        };
    }

    public function tracksInventoryByDefault(): bool
    {
        return $this === self::Goods;
    }

    /**
     * @return list<self>
     */
    public static function sorted(): array
    {
        $cases = self::cases();

        usort($cases, fn (self $left, self $right): int => $left->sortOrder() <=> $right->sortOrder());

        return $cases;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
