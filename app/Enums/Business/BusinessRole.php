<?php

namespace App\Enums\Business;

enum BusinessRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Viewer = 'viewer';

    public function label(): string
    {
        $key = 'general.business_role_'.$this->value;
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
