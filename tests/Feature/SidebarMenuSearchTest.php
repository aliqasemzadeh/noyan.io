<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation\SidebarMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class SidebarMenuSearchTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_system_dashboard_renders_menu_command_palette(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)
            ->get(route('system.dashboard'))
            ->assertOk()
            ->assertSee('sidebar.menu-search', false)
            ->assertSee('data-flux-sidebar-search', false)
            ->assertSee(__('general.search_menu'));
    }

    public function test_administrator_sees_permitted_system_menu_items(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)->get(route('system.dashboard'))->assertOk();

        $labels = collect(SidebarMenu::searchItems($user))->pluck('label');

        $this->assertTrue($labels->contains(__('general.dashboard')));
        $this->assertTrue($labels->contains(__('general.users')));
        $this->assertTrue($labels->contains(__('general.roles')));
    }

    public function test_user_without_user_view_does_not_see_users_in_search(): void
    {
        $user = $this->grantPermissions(
            User::factory()->create(),
            'dashboard_view',
            'role_view',
        );

        $this->actingAs($user)->get(route('system.dashboard'))->assertOk();

        $labels = collect(SidebarMenu::searchItems($user))->pluck('label');

        $this->assertTrue($labels->contains(__('general.dashboard')));
        $this->assertTrue($labels->contains(__('general.roles')));
        $this->assertFalse($labels->contains(__('general.users')));
    }

    public function test_user_panel_search_items_are_fixed_set(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('user.dashboard'))->assertOk();

        $labels = collect(SidebarMenu::searchItems($user))->pluck('label')->all();

        $this->assertSame([
            __('general.dashboard'),
            __('general.my_businesses'),
            __('general.support'),
        ], $labels);
    }
}
