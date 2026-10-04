<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Support\AdministratorPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait GrantsAdministratorAccess
{
    protected function seedAdministratorPermissions(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (AdministratorPermissions::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $role = Role::findOrCreate('administrator', 'web');
        $role->syncPermissions(AdministratorPermissions::names());
    }

    protected function grantAdministratorAccess(User $user): User
    {
        $this->seedAdministratorPermissions();

        $user->assignRole('administrator');

        return $user;
    }

    protected function grantPermissions(User $user, string ...$permissions): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}
