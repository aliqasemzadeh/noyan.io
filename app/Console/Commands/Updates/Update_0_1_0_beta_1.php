<?php

namespace App\Console\Commands\Updates;

/**
 * First packaged Accounting & GL bootstrap + historical backfill.
 */
class Update_0_1_0_beta_1 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.1';
    }

    public function artisanCommands(): array
    {
        return [
            ['accounting:seed-coa', '--all'],
            ['accounting:sync-gl-details', '--all'],
            ['accounting:migrate-categories-to-gl', '--all'],
            ['accounting:backfill-gl', '--all', '--skip-seed'],
        ];
    }
}
