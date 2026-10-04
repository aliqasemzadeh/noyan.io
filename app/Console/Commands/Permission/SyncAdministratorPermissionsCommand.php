<?php

namespace App\Console\Commands\Permission;

use App\Support\SyncAdministratorPermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync')]
#[Description('Sync administrator permissions from lang/permissions.php into the database')]
class SyncAdministratorPermissionsCommand extends Command
{
    public function handle(SyncAdministratorPermissions $syncAdministratorPermissions): int
    {
        $result = $syncAdministratorPermissions->handle();

        $this->info(sprintf(
            'Synced %d permissions to the "%s" role.',
            $result['permissions'],
            $result['role'],
        ));

        return self::SUCCESS;
    }
}
