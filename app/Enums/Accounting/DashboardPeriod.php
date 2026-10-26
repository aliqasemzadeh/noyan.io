<?php

namespace App\Enums\Accounting;

enum DashboardPeriod: string
{
    case Today = 'today';
    case Month = 'month';
    case Quarter = 'quarter';
    case FiscalYear = 'fiscal_year';

    public function label(): string
    {
        return match ($this) {
            self::Today => __('general.dashboard_period_today'),
            self::Month => __('general.dashboard_period_month'),
            self::Quarter => __('general.dashboard_period_quarter'),
            self::FiscalYear => __('general.dashboard_period_fiscal_year'),
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
