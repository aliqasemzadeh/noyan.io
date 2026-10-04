<?php

namespace App\Enums\Accounting;

enum JournalEntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('general.journal_status_draft'),
            self::Posted => __('general.journal_status_posted'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::Posted => 'green',
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
