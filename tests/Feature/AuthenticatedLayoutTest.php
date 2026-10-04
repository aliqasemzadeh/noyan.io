<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class AuthenticatedLayoutTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_users_to_login(): void
    {
        $this->get(route('system.users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dashboard_layout(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)
            ->get(route('system.dashboard'))
            ->assertOk()
            ->assertSee(__('general.dashboard'))
            ->assertSee(__('general.system_management'))
            ->assertSee(__('general.accounting'))
            ->assertSee($user->mobile);
    }

    public function test_authenticated_user_can_view_users_shell(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)
            ->get(route('system.users.index'))
            ->assertOk()
            ->assertSee(__('general.users'))
            ->assertSee(__('general.ai_prompt'));
    }

    public function test_dashboard_shows_current_business_name(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());
        $business = Business::factory()->for($user, 'owner')->create([
            'name' => 'Noyan Shop',
        ]);

        $user->forceFill(['current_business_id' => $business->id])->save();

        $this->actingAs($user->fresh())
            ->get(route('system.dashboard'))
            ->assertOk()
            ->assertSee('Noyan Shop');
    }
}
