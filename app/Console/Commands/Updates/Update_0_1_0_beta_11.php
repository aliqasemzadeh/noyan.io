<?php

namespace App\Console\Commands\Updates;

/**
 * Accounting AI: create sale/purchase invoices from text (create_invoice tool).
 * No one-shot artisan work.
 */
class Update_0_1_0_beta_11 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.11';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
