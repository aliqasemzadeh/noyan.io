<?php

namespace App\Support\System;

use App\Settings\SystemUpdateSettings;
use Throwable;

class AppVersion
{
    public static function current(): string
    {
        return (string) config('noyan.version', '0.0.0');
    }

    public static function installed(): string
    {
        try {
            $installed = app(SystemUpdateSettings::class)->installed_version;

            return filled($installed) ? (string) $installed : '0.0.0';
        } catch (Throwable) {
            return '0.0.0';
        }
    }

    public static function markInstalled(string $version): void
    {
        $settings = app(SystemUpdateSettings::class);
        $settings->installed_version = $version;
        $settings->save();
    }

    public static function isPending(string $releaseVersion, ?string $installedVersion = null): bool
    {
        $installed = $installedVersion ?? self::installed();

        return version_compare($releaseVersion, $installed, '>');
    }
}
