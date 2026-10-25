<?php

namespace App\Enums\Accounting;

enum VoucherNumberingMode: string
{
    case Yearly = 'yearly';
    case Monthly = 'monthly';
    case Daily = 'daily';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Yearly => __('general.voucher_numbering_mode_yearly'),
            self::Monthly => __('general.voucher_numbering_mode_monthly'),
            self::Daily => __('general.voucher_numbering_mode_daily'),
            self::Manual => __('general.voucher_numbering_mode_manual'),
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
