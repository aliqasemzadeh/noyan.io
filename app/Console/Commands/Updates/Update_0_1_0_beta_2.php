<?php

namespace App\Console\Commands\Updates;

/**
 * Persian labels for the standard chart of accounts (re-seed upserts names).
 */
class Update_0_1_0_beta_2 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.2';
    }

    public function artisanCommands(): array
    {
        return [
            ['accounting:seed-coa', '--all'],
            ['accounting:sync-gl-details', '--all'],
        ];
    }
}
