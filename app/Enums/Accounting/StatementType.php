<?php

namespace App\Enums\Accounting;

enum StatementType: string
{
    case BalanceSheet = 'balance_sheet';
    case IncomeStatement = 'income_statement';
    case Memorandum = 'memorandum';

    public function label(): string
    {
        return match ($this) {
            self::BalanceSheet => __('general.statement_type_balance_sheet'),
            self::IncomeStatement => __('general.statement_type_income_statement'),
            self::Memorandum => __('general.statement_type_memorandum'),
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
