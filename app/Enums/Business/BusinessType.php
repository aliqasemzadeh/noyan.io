<?php

namespace App\Enums\Business;

enum BusinessType: string
{
    case Store = 'store';
    case Service = 'service';
    case Personal = 'personal';

    public function label(): string
    {
        $key = 'business_types.'.$this->value;
        $translated = __($key);

        return $translated === $key ? $this->name : $translated;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
