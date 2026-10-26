<?php

namespace App\Console\Commands\Updates;

/**
 * Floating detail levels are stored as rows, so a subsidiary can carry
 * level 4 and any levels after it. Re-seed keeps system charts aligned.
 */
class Update_0_1_0_beta_4 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.4';
    }

    public function artisanCommands(): array
    {
        return [
            ['accounting:seed-coa', '--all'],
        ];
    }
}
