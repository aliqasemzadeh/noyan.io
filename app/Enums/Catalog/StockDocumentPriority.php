<?php

namespace App\Enums\Catalog;

enum StockDocumentPriority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => __('general.priority_normal'),
            self::Urgent => __('general.priority_urgent'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Normal => 'zinc',
            self::Urgent => 'rose',
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
