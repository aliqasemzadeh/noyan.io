<?php

namespace App\Enums;

enum PartyType: string
{
    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Individual => __('general.party_type_individual'),
            self::Company => __('general.party_type_company'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Individual => 'sky',
            self::Company => 'violet',
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
