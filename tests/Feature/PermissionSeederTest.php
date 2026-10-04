<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdministratorPermissions;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_seeder_creates_catalog_and_assigns_administrator(): void
    {
        $this->seed(PermissionSeeder::class);

        foreach (AdministratorPermissions::names() as $name) {
            $this->assertDatabaseHas('permissions', [
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::query()->where('name', 'administrator')->first();
        $this->assertNotNull($role);
        $this->assertSame(
            count(AdministratorPermissions::names()),
            $role->permissions()->count(),
        );

        $user = User::query()->where('mobile', '09123456789')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('administrator'));
        $this->assertTrue($user->can('user_view'));
        $this->assertTrue($user->can('business_view'));

        $this->assertSame(
            count(AdministratorPermissions::names()),
            Permission::query()->count(),
        );
    }
}
