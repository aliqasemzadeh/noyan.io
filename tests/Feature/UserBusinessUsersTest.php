<?php

namespace Tests\Feature;

use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserBusinessUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_business_users_page(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $owner->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($owner)
            ->test('pages::panel.user.business.users', ['business' => $business])
            ->assertOk()
            ->assertSee($owner->mobile);
    }

    public function test_non_owner_member_cannot_open_business_users_page(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        BusinessUser::factory()
            ->seller()
            ->for($business)
            ->for($member, 'user')
            ->create();

        $member->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($member)
            ->test('pages::panel.user.business.users', ['business' => $business])
            ->assertForbidden();
    }

    public function test_owner_can_update_existing_member_with_custom_permissions_by_mobile(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['mobile' => '09120001122']);
        $business = Business::factory()->for($owner, 'owner')->create([
            'sms_wallet_balance' => '5000',
        ]);
        $owner->forceFill(['current_business_id' => $business->id])->save();

        BusinessUser::factory()
            ->seller()
            ->for($business)
            ->for($member, 'user')
            ->create();

        $customPermissions = [
            BusinessPermission::QuoteView->value,
            BusinessPermission::InvoiceView->value,
            BusinessPermission::PartyView->value,
            BusinessPermission::PartyCreate->value,
        ];

        Livewire::actingAs($owner)
            ->test('business.create-user')
            ->call('assignData', $business)
            ->set('form.mobile', '09120001122')
            ->set('form.roles', [BusinessRole::Seller->value])
            ->set('form.permissions', [
                BusinessRole::Seller->value => $customPermissions,
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('panels.user.business.users.table');

        $membership = BusinessUser::query()
            ->where('business_id', $business->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertTrue($membership->hasRole(BusinessRole::Seller));
        $this->assertSame($customPermissions, $membership->permissionsFor(BusinessRole::Seller));
    }

    public function test_owner_can_update_member_permissions(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $owner->forceFill(['current_business_id' => $business->id])->save();

        $membership = BusinessUser::factory()
            ->seller()
            ->for($business)
            ->for($member, 'user')
            ->create();

        $updatedPermissions = [
            BusinessPermission::QuoteView->value,
            BusinessPermission::InvoiceView->value,
            BusinessPermission::PartyView->value,
        ];

        Livewire::actingAs($owner)
            ->test('business.edit-user')
            ->call('assignData', $membership)
            ->set('form.roles', [BusinessRole::Seller->value])
            ->set('form.permissions', [
                BusinessRole::Seller->value => $updatedPermissions,
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('panels.user.business.users.table');

        $membership->refresh();

        $this->assertSame($updatedPermissions, $membership->permissionsFor(BusinessRole::Seller));
        $this->assertFalse($membership->hasPermission(BusinessPermission::PartyCreate));
    }

    public function test_owner_cannot_edit_owner_membership(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $owner->forceFill(['current_business_id' => $business->id])->save();

        $ownerMembership = BusinessUser::query()
            ->where('business_id', $business->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        Livewire::actingAs($owner)
            ->test('business.edit-user')
            ->call('assignData', $ownerMembership)
            ->assertForbidden();
    }

    public function test_non_owner_cannot_assign_create_user_data(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        BusinessUser::factory()
            ->seller()
            ->for($business)
            ->for($member, 'user')
            ->create();

        $member->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($member)
            ->test('business.create-user')
            ->call('assignData', $business)
            ->assertForbidden();
    }

    public function test_owner_can_remove_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $owner->forceFill(['current_business_id' => $business->id])->save();

        $membership = BusinessUser::factory()
            ->accountant()
            ->for($business)
            ->for($member, 'user')
            ->create();

        Livewire::actingAs($owner)
            ->test('business.remove-user')
            ->call('assignData', $membership)
            ->call('remove')
            ->assertHasNoErrors()
            ->assertDispatched('panels.user.business.users.table');

        $this->assertSoftDeleted($membership);
    }
}
