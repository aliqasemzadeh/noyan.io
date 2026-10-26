<?php

namespace App\Enums\Accounting;

enum AutoPostQueueStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Skipped = 'skipped';
    case Voided = 'voided';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
