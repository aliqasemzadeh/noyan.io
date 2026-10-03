<?php

namespace App\Enums\Accounting;

enum InvoicePaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('general.payment_status_unpaid'),
            self::PartiallyPaid => __('general.payment_status_partially_paid'),
            self::Paid => __('general.payment_status_paid'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Unpaid => 'zinc',
            self::PartiallyPaid => 'amber',
            self::Paid => 'green',
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
