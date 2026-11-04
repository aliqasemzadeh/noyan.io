<?php

namespace App\Console\Commands\Updates;

/**
 * Persist transaction balance_after / destination_balance_after and rebuild
 * account current balances from ledger history.
 */
class Update_0_1_0_beta_15 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.15';
    }

    public function artisanCommands(): array
    {
        return [
            ['accounting:rebuild-balances', '--all'],
        ];
    }
}
