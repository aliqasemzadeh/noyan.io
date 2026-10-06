<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncAdministratorPermissions
{
    /**
     * @return array{permissions: int, role: string}
     */
    public function handle(): array
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionNames = AdministratorPermissions::names();

        foreach ($permissionNames as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // WithoutModelEvents (used by DatabaseSeeder) skips RefreshesPermissionCache,
        // so the registrar can still hold an empty collection after findOrCreate.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $administrator = Role::findOrCreate('administrator', 'web');
        $administrator->syncPermissions($permissionNames);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return [
            'permissions' => count($permissionNames),
            'role' => $administrator->name,
        ];
    }
}
