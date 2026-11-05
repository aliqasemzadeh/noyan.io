<?php

namespace App\Enums\Accounting;

enum CreditDisplayStyle: string
{
    case Parentheses = 'parentheses';
    case Minus = 'minus';

    public function label(): string
    {
        return match ($this) {
            self::Parentheses => __('general.credit_display_style_parentheses'),
            self::Minus => __('general.credit_display_style_minus'),
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
