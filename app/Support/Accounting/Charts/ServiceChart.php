<?php

namespace App\Support\Accounting\Charts;

final class ServiceChart
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return ChartTree::withoutSystemKeys(TradingChart::definitions(), [
            'sales',
            'inventory',
            'cogs',
        ]);
    }
}
