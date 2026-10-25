<?php

namespace App\Enums\Accounting;

enum AccountLevel: int
{
    case Group = 1;
    case Control = 2;
    case Subsidiary = 3;

    public function label(): string
    {
        return match ($this) {
            self::Group => __('general.account_level_group'),
            self::Control => __('general.account_level_control'),
            self::Subsidiary => __('general.account_level_subsidiary'),
        };
    }

    public function childLevel(): ?self
    {
        return match ($this) {
            self::Group => self::Control,
            self::Control => self::Subsidiary,
            self::Subsidiary => null,
        };
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
