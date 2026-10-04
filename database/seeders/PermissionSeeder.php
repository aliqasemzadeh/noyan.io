<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AdministratorPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionNames = AdministratorPermissions::names();

        foreach ($permissionNames as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $administrator = Role::findOrCreate('administrator', 'web');
        $administrator->syncPermissions($permissionNames);

        $seedUser = User::query()->firstOrCreate([
            'mobile' => '09123456789',
        ]);

        if (! $seedUser->hasRole('administrator')) {
            $seedUser->assignRole($administrator);
        }
    }
}
