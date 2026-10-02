<?php

namespace App\Enums;

enum CurrencyType: string
{
    case Fiat = 'fiat';
    case Crypto = 'crypto';
    case Commodity = 'commodity';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Fiat => __('general.currency_type_fiat'),
            self::Crypto => __('general.currency_type_crypto'),
            self::Commodity => __('general.currency_type_commodity'),
            self::Custom => __('general.currency_type_custom'),
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
