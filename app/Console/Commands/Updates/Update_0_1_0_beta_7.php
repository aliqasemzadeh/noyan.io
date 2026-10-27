<?php

namespace App\Console\Commands\Updates;

/**
 * Mobile shell/dashboard responsive fixes and Flux input dir=ltr icon
 * positioning (published input wrapper). No one-shot artisan work.
 */
class Update_0_1_0_beta_7 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.7';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
