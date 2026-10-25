<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public string $site_short_name;

    public string $site_description;

    public ?string $site_logo;

    public ?string $site_favicon;

    public string $locale;

    public string $timezone;

    public static function group(): string
    {
        return 'general';
    }
}
