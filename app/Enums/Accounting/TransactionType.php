<?php

namespace App\Enums\Accounting;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Income => __('general.transaction_type_income'),
            self::Expense => __('general.transaction_type_expense'),
            self::Transfer => __('general.transaction_type_transfer'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Income => 'green',
            self::Expense => 'rose',
            self::Transfer => 'sky',
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
