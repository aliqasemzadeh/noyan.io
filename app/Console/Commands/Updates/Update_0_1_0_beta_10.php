<?php

namespace App\Console\Commands\Updates;

/**
 * Welcome page branding from GeneralSettings, Flux dark/light toggle fix,
 * and Noyan brand theme colors (#071F43 / #16719D / #2DE1C2).
 * No one-shot artisan work.
 */
class Update_0_1_0_beta_10 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.10';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
