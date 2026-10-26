<?php

namespace App\Support\Accounting\Charts;

final class ContractingChart
{
    /**
     * Income and expense leaves that must carry a project detail.
     *
     * @var list<string>
     */
    private const PROJECT_KEYS = [
        'sales',
        'services',
        'sales_discount',
        'cogs',
        'rent_expense',
        'payroll_expense',
        'bank_fees',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return ChartTree::requireProjectDetail(TradingChart::definitions(), self::PROJECT_KEYS);
    }
}
