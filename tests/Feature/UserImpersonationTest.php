<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_admin_can_impersonate_regular_user(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $user = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.user-management.user.index')
            ->call('impersonate', $user->id)
            ->assertRedirect(route('user.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Impersonation::isImpersonating());
        $this->assertSame($admin->id, Impersonation::impersonatorId());
    }

    public function test_admin_cannot_impersonate_another_administrator(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $otherAdmin = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.user-management.user.index')
            ->call('impersonate', $otherAdmin->id)
            ->assertOk();

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(Impersonation::isImpersonating());
    }

    public function test_leave_impersonation_returns_to_admin(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $user = User::factory()->create();

        $this->actingAs($user);
        session([Impersonation::SESSION_KEY => $admin->id]);

        Livewire::test('layout.impersonation-banner')
            ->call('leave')
            ->assertRedirect(route('system.users.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(Impersonation::isImpersonating());
    }
}
