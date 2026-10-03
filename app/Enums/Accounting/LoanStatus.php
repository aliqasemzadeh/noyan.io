<?php

namespace App\Enums\Accounting;

enum LoanStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Defaulted = 'defaulted';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('general.loan_status_active'),
            self::Completed => __('general.loan_status_completed'),
            self::Defaulted => __('general.loan_status_defaulted'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'amber',
            self::Completed => 'green',
            self::Defaulted => 'rose',
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
