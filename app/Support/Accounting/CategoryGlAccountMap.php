<?php

namespace App\Support\Accounting;

use App\Enums\Accounting\TransactionType;
use App\Enums\CategoryType;
use App\Models\Accounting\GlAccount;
use App\Models\Business;
use App\Models\Category;

class CategoryGlAccountMap
{
    /**
     * Category code → COA system_key.
     *
     * @var array<string, string>
     */
    public const CODE_TO_SYSTEM_KEY = [
        'sales' => 'sales',
        'services' => 'services',
        'other_income' => 'sales',
        'interest' => 'fx_gain',
        'fx_gain' => 'fx_gain',
        'receivable' => 'accounts_receivable',
        'payable' => 'accounts_payable',
        'cash_bank' => 'cash_and_bank',
        'inventory' => 'inventory',
        'tax_payable' => 'vat_payable',
        'tax' => 'vat_payable',
        'rent' => 'rent_expense',
        'utilities' => 'rent_expense',
        'office_supplies' => 'rent_expense',
        'maintenance' => 'rent_expense',
        'marketing' => 'rent_expense',
        'transport' => 'rent_expense',
        'salary' => 'payroll_expense',
        'insurance' => 'payroll_expense',
        'payroll_tax' => 'payroll_expense',
        'payroll' => 'payroll_expense',
        'bank_fee' => 'bank_fees',
        'property' => 'ppe',
        'equipment' => 'ppe',
        'short_term_loan' => 'short_term_loans',
        'long_term_loan' => 'long_term_loans',
    ];

    public static function systemKeyForCategory(?Category $category): ?string
    {
        if ($category === null) {
            return null;
        }

        return self::CODE_TO_SYSTEM_KEY[$category->code] ?? null;
    }

    public static function resolve(Business|int $business, ?Category $category): ?GlAccount
    {
        if ($category === null) {
            return null;
        }

        if ($category->gl_account_id !== null) {
            $direct = GlAccount::query()->find($category->gl_account_id);

            if ($direct !== null) {
                return $direct;
            }
        }

        $systemKey = self::systemKeyForCategory($category);

        if ($systemKey !== null) {
            $mapped = GlAccount::findBySystemKey($business, $systemKey);

            if ($mapped !== null) {
                return $mapped;
            }
        }

        return match ($category->type) {
            CategoryType::Income => GlAccount::findBySystemKey($business, 'sales'),
            CategoryType::Expense => GlAccount::findBySystemKey($business, 'rent_expense'),
            CategoryType::Asset => GlAccount::findBySystemKey($business, 'inventory'),
            CategoryType::Liability => GlAccount::findBySystemKey($business, 'accounts_payable'),
            default => null,
        };
    }

    public static function defaultCategoryCode(TransactionType $type): ?string
    {
        return match ($type) {
            TransactionType::Income => 'other_income',
            TransactionType::Expense => 'bank_fee',
            TransactionType::Transfer => null,
        };
    }

    public static function defaultLeafCategory(int $businessId, TransactionType $type): ?Category
    {
        $code = self::defaultCategoryCode($type);

        if ($code === null) {
            return null;
        }

        $categoryType = match ($type) {
            TransactionType::Income => CategoryType::Income,
            TransactionType::Expense => CategoryType::Expense,
            TransactionType::Transfer => null,
        };

        if ($categoryType === null) {
            return null;
        }

        return Category::query()
            ->availableToBusiness($businessId)
            ->ofType($categoryType)
            ->where('code', $code)
            ->whereDoesntHave('children')
            ->orderByRaw('business_id is null')
            ->orderBy('id')
            ->first();
    }
}
