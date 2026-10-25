<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SecuritySettings extends Settings
{
    /** @var array<int, string> */
    public array $banned_ips;

    /** @var array<int, string> */
    public array $banned_mobiles;

    public static function group(): string
    {
        return 'security';
    }
}
