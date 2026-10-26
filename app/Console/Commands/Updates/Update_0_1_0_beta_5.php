<?php

namespace App\Console\Commands\Updates;

/**
 * Warehouse documents ship with the migration. Stored business roles still
 * need the new count and inventory-value permissions added once.
 */
class Update_0_1_0_beta_5 extends VersionUpdate
{
    public static function version(): string
    {
        return '0.1.0-beta.5';
    }

    public function artisanCommands(): array
    {
        return [
            ['business:grant-warehouse-permissions'],
        ];
    }
}
