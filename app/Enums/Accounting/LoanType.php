<?php

namespace App\Enums\Accounting;

enum LoanType: string
{
    case Received = 'received';
    case Given = 'given';

    public function label(): string
    {
        return match ($this) {
            self::Received => __('general.loan_type_received'),
            self::Given => __('general.loan_type_given'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Received => 'rose',
            self::Given => 'teal',
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
