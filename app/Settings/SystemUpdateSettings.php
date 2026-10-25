<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SystemUpdateSettings extends Settings
{
    /**
     * Last successfully applied application version (semver / semver-prerelease).
     */
    public string $installed_version;

    public static function group(): string
    {
        return 'system_update';
    }
}
