<?php

namespace App\Console\Commands\Updates;

/**
 * User home dashboard rewrite: profile moves to /user/profile and /user
 * becomes a business, panel, and ticket summary. No one-shot artisan work.
 */
class Update_0_1_0_beta_6 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.6';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
