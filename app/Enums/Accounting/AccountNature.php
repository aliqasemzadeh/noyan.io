<?php

namespace App\Enums\Accounting;

enum AccountNature: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Debit => __('general.account_nature_debit'),
            self::Credit => __('general.account_nature_credit'),
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
