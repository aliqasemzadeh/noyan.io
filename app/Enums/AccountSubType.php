<?php

namespace App\Enums;

enum AccountSubType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case PettyCash = 'petty_cash';
    case Wallet = 'wallet';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('general.account_sub_type_cash'),
            self::Bank => __('general.account_sub_type_bank'),
            self::PettyCash => __('general.account_sub_type_petty_cash'),
            self::Wallet => __('general.account_sub_type_wallet'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Cash => 'emerald',
            self::Bank => 'blue',
            self::PettyCash => 'amber',
            self::Wallet => 'violet',
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
