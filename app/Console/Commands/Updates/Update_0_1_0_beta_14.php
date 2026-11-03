<?php

namespace App\Console\Commands\Updates;

/**
 * Account ledger / transaction list: running balance_after column;
 * create transaction from account view with prefilled account.
 * No one-shot artisan work.
 */
class Update_0_1_0_beta_14 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.14';
    }

    public function artisanCommands(): array
    {
        return [];
    }
}
