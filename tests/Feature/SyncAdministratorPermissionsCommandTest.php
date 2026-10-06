<?php

namespace Tests\Feature;

use App\Support\AdministratorPermissions;
use App\Support\SyncAdministratorPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncAdministratorPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_sync_command_creates_catalog_and_assigns_administrator(): void
    {
        $this->artisan('permissions:sync')
            ->expectsOutputToContain('Synced')
            ->assertSuccessful();

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
        $this->assertSame(
            count(AdministratorPermissions::names()),
            Permission::query()->count(),
        );
    }

    public function test_permissions_sync_command_updates_administrator_when_catalog_grows(): void
    {
        $this->artisan('permissions:sync')->assertSuccessful();

        Permission::findOrCreate('legacy_permission', 'web');

        $role = Role::query()->where('name', 'administrator')->firstOrFail();
        $role->givePermissionTo('legacy_permission');

        $this->artisan('permissions:sync')->assertSuccessful();

        $role->refresh();

        $this->assertFalse($role->hasPermissionTo('legacy_permission'));
        $this->assertSame(
            count(AdministratorPermissions::names()),
            $role->permissions()->count(),
        );
    }

    public function test_sync_works_when_eloquent_model_events_are_disabled(): void
    {
        Model::withoutEvents(function (): void {
            app(SyncAdministratorPermissions::class)->handle();
        });

        $role = Role::query()->where('name', 'administrator')->first();

        $this->assertNotNull($role);
        $this->assertSame(
            count(AdministratorPermissions::names()),
            $role->permissions()->count(),
        );
    }
}
