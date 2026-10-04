<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_user_without_permission_cannot_view_businesses(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('system.businesses.index'))
            ->assertForbidden();
    }

    public function test_user_without_permission_cannot_view_roles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('system.roles.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_roles_and_permissions(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($admin)
            ->get(route('system.roles.index'))
            ->assertOk()
            ->assertSee(__('general.roles'));

        $this->actingAs($admin)
            ->get(route('system.permissions.index'))
            ->assertOk()
            ->assertSee(__('general.permissions'));
    }

    public function test_user_access_page_can_toggle_role_and_permission(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create([
            'mobile' => '09121111111',
        ]));
        $target = User::factory()->create([
            'mobile' => '09172222222',
        ]);

        $role = Role::findOrCreate('manager', 'web');
        $permission = Permission::findOrCreate('business_view', 'web');

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.user-management.user.access', ['user' => $target])
            ->call('toggleRole', $role->id)
            ->assertHasNoErrors();

        $this->assertTrue($target->fresh()->hasRole('manager'));

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.user-management.user.access', ['user' => $target])
            ->call('togglePermission', $permission->id)
            ->assertHasNoErrors();

        $this->assertTrue($target->fresh()->hasDirectPermission('business_view'));

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.user-management.user.access', ['user' => $target])
            ->call('toggleRole', $role->id)
            ->call('togglePermission', $permission->id)
            ->assertHasNoErrors();

        $this->assertFalse($target->fresh()->hasRole('manager'));
        $this->assertFalse($target->fresh()->hasDirectPermission('business_view'));
    }

    public function test_role_access_page_can_toggle_permission(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $role = Role::findOrCreate('editor', 'web');
        $permission = Permission::findOrCreate('currency_view', 'web');

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.user-management.role.access', ['role' => $role])
            ->call('togglePermission', $permission->id)
            ->assertHasNoErrors();

        $this->assertTrue($role->fresh()->hasPermissionTo('currency_view'));

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.user-management.role.access', ['role' => $role])
            ->call('togglePermission', $permission->id)
            ->assertHasNoErrors();

        $this->assertFalse($role->fresh()->hasPermissionTo('currency_view'));
    }

    public function test_role_can_be_created_via_component(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($admin)
            ->test('role.create')
            ->set('form.name', 'support')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('panels.administrator.role.index.table');

        $this->assertDatabaseHas('roles', [
            'name' => 'support',
            'guard_name' => 'web',
        ]);
    }
}
