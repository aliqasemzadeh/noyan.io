<?php

namespace App\Enums\Accounting;

enum InvoiceAging: string
{
    case Current = 'current';
    case Days1To30 = '1_30';
    case Days31To60 = '31_60';
    case Over60 = 'over_60';

    public function label(): string
    {
        return match ($this) {
            self::Current => __('general.dashboard_aging_current'),
            self::Days1To30 => __('general.dashboard_aging_1_30'),
            self::Days31To60 => __('general.dashboard_aging_31_60'),
            self::Over60 => __('general.dashboard_aging_over_60'),
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
