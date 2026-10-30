<?php

namespace App\Console\Commands\Updates;

/**
 * Journal treasury sync: convert posted manual cash↔party and cash↔cash
 * transfer vouchers into linked treasury transactions (balances + SoftDeletes).
 */
class Update_0_1_0_beta_13 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.13';
    }

    public function artisanCommands(): array
    {
        return [
            ['accounting:convert-manual-settlements', '--all'],
        ];
    }
}
