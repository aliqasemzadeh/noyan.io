<?php

namespace App\Enums\Catalog;

enum StockCountStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('general.stock_count_status_'.$this->value);
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Resolved => 'teal',
            self::Cancelled => 'zinc',
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
