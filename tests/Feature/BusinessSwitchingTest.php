<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_belong_to_multiple_businesses(): void
    {
        $user = User::factory()->create();
        $first = Business::factory()->for($user, 'owner')->create(['name' => 'First Co']);
        $second = Business::factory()->create(['name' => 'Second Co']);

        BusinessUser::factory()->create([
            'business_id' => $second->id,
            'user_id' => $user->id,
            'role' => BusinessRole::Admin,
        ]);

        $this->assertTrue($user->belongsToBusiness($first));
        $this->assertTrue($user->belongsToBusiness($second));
        $this->assertCount(2, $user->businesses);
    }

    public function test_user_can_switch_current_business(): void
    {
        $user = User::factory()->create();
        $first = Business::factory()->for($user, 'owner')->create();
        $second = Business::factory()->create();

        BusinessUser::factory()->create([
            'business_id' => $second->id,
            'user_id' => $user->id,
            'role' => BusinessRole::Viewer,
        ]);

        $user->switchBusiness($second);

        $this->assertSame($second->id, $user->fresh()->current_business_id);
    }

    public function test_user_cannot_switch_to_business_without_membership(): void
    {
        $user = User::factory()->create();
        Business::factory()->for($user, 'owner')->create();
        $foreign = Business::factory()->create();

        $this->expectException(AuthorizationException::class);

        $user->switchBusiness($foreign);
    }

    public function test_app_shell_switches_business_for_member(): void
    {
        $user = User::factory()->create();
        $first = Business::factory()->for($user, 'owner')->create();
        $second = Business::factory()->create();

        BusinessUser::factory()->create([
            'business_id' => $second->id,
            'user_id' => $user->id,
            'role' => BusinessRole::Admin,
        ]);

        $user->forceFill(['current_business_id' => $first->id])->save();

        Livewire::actingAs($user)
            ->test('layout.app-shell', ['variant' => 'sidebar'])
            ->call('switchBusiness', $second->id)
            ->assertHasNoErrors();

        $this->assertSame($second->id, $user->fresh()->current_business_id);
    }

    public function test_app_shell_rejects_switch_for_non_member(): void
    {
        $user = User::factory()->create();
        Business::factory()->for($user, 'owner')->create();
        $foreign = Business::factory()->create();

        Livewire::actingAs($user)
            ->test('layout.app-shell', ['variant' => 'sidebar'])
            ->call('switchBusiness', $foreign->id)
            ->assertHasNoErrors();

        $this->assertNotSame($foreign->id, $user->fresh()->current_business_id);
    }

    public function test_business_and_membership_support_soft_deletes(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $membership = BusinessUser::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $membership->delete();
        $business->delete();

        $this->assertSoftDeleted($membership);
        $this->assertSoftDeleted($business);
        $this->assertFalse($user->fresh()->belongsToBusiness($business));
    }
}
