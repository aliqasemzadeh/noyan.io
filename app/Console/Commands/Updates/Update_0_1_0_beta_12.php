<?php

namespace App\Console\Commands\Updates;

/**
 * Purchase invoice UI labels (نوع خرید), AI create_invoice asks sale vs purchase.
 * No one-shot artisan work.
 */
class Update_0_1_0_beta_12 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.12';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
