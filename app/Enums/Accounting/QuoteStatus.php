<?php

namespace App\Enums\Accounting;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Expired = 'expired';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('general.quote_status_draft'),
            self::Finalized => __('general.quote_status_finalized'),
            self::Expired => __('general.quote_status_expired'),
            self::Converted => __('general.quote_status_converted'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Finalized => 'sky',
            self::Expired => 'rose',
            self::Converted => 'green',
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
