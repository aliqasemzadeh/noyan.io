<?php

namespace App\Enums\Accounting;

use App\Support\Accounting\Charts\ContractingChart;
use App\Support\Accounting\Charts\ServiceChart;
use App\Support\Accounting\Charts\TradingChart;

enum ChartTemplate: string
{
    case Trading = 'trading';
    case Service = 'service';
    case Contracting = 'contracting';

    public function label(): string
    {
        return __('general.chart_template_'.$this->value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return match ($this) {
            self::Trading => TradingChart::definitions(),
            self::Service => ServiceChart::definitions(),
            self::Contracting => ContractingChart::definitions(),
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
