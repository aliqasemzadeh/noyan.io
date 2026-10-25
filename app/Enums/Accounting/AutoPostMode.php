<?php

namespace App\Enums\Accounting;

enum AutoPostMode: string
{
    case Off = 'off';
    case Realtime = 'realtime';
    case ManualReview = 'manual_review';
    case DailyBatch = 'daily_batch';

    public function label(): string
    {
        return match ($this) {
            self::Off => __('general.auto_post_mode_off'),
            self::Realtime => __('general.auto_post_mode_realtime'),
            self::ManualReview => __('general.auto_post_mode_manual_review'),
            self::DailyBatch => __('general.auto_post_mode_daily_batch'),
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
