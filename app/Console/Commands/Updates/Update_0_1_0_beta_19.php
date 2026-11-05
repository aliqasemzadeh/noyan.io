<?php

namespace App\Console\Commands\Updates;

/**
 * Signed amount display: credit balances in parentheses or minus (ledger setting),
 * green debit / red credit colors, and colored direction icons on transactions.
 * Schema via migration only — no one-shot artisan work.
 */
class Update_0_1_0_beta_19 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.19';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
