<?php

namespace App\Enums;

enum CategoryType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Asset = 'asset';
    case Liability = 'liability';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::Income => __('general.category_type_income'),
            self::Expense => __('general.category_type_expense'),
            self::Asset => __('general.category_type_asset'),
            self::Liability => __('general.category_type_liability'),
            self::Product => __('general.category_type_product'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Income => 'green',
            self::Expense => 'rose',
            self::Asset => 'sky',
            self::Liability => 'amber',
            self::Product => 'violet',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function accountingCases(): array
    {
        return [
            self::Income,
            self::Expense,
            self::Asset,
            self::Liability,
        ];
    }
}
