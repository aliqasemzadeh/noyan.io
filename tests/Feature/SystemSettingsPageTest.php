<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class SystemSettingsPageTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_settings_page(): void
    {
        $this->get(route('system.settings.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_view_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('system.settings.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_settings_page(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)
            ->get(route('system.settings.index'))
            ->assertOk()
            ->assertSee(__('general.system_settings'))
            ->assertSee(__('general.system_settings_placeholder'));
    }
}
