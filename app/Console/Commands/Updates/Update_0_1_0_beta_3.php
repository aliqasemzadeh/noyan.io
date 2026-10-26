<?php

namespace App\Console\Commands\Updates;

/**
 * Industry chart templates: trading, service, and contracting.
 * Upsert does not delete custom accounts.
 */
class Update_0_1_0_beta_3 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.3';
    }

    public function artisanCommands(): array
    {
        return [
            ['accounting:seed-coa', '--all'],
            ['accounting:sync-gl-details', '--all'],
        ];
    }
}
