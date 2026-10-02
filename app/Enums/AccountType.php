<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';

    public function label(): string
    {
        return match ($this) {
            self::Asset => __('general.account_type_asset'),
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
