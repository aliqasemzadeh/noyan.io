<?php

namespace App\Console\Commands\Updates;

/**
 * Journal entry form enhancements and permanent login session/cookie fixes.
 * No one-shot artisan work.
 */
class Update_0_1_0_beta_9 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.9';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
