<?php

namespace App\Enums\Accounting;

enum JournalEntryStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('general.journal_status_draft'),
            self::PendingReview => __('general.journal_status_pending_review'),
            self::Posted => __('general.journal_status_posted'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::PendingReview => 'sky',
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
