<?php

namespace Tests\Feature;

use App\Enums\Business\BusinessRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_business_with_owner_membership(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create(['mobile' => '09121234567']);

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.business.index')
            ->assertOk();

        Livewire::actingAs($admin)
            ->test('business.create')
            ->set('form.name', 'Noyan Co')
            ->set('form.owner_id', $owner->id)
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $business = Business::query()->where('name', 'Noyan Co')->first();

        $this->assertNotNull($business);
        $this->assertSame($owner->id, $business->owner_id);
        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'role' => BusinessRole::Owner->value,
        ]);
        $this->assertSame($business->id, $owner->fresh()->current_business_id);
    }

    public function test_admin_can_update_business_owner(): void
    {
        $admin = User::factory()->create();
        $oldOwner = User::factory()->create();
        $newOwner = User::factory()->create();
        $business = Business::factory()->for($oldOwner, 'owner')->create([
            'name' => 'Old Name',
        ]);

        Livewire::actingAs($admin)
            ->test('business.edit')
            ->call('assignData', $business)
            ->set('form.name', 'New Name')
            ->set('form.owner_id', $newOwner->id)
            ->call('save')
            ->assertHasNoErrors();

        $business->refresh();

        $this->assertSame('New Name', $business->name);
        $this->assertSame($newOwner->id, $business->owner_id);

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $newOwner->id,
            'role' => BusinessRole::Owner->value,
        ]);

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $oldOwner->id,
            'role' => BusinessRole::Admin->value,
        ]);
    }

    public function test_admin_can_add_and_remove_business_user(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($admin)
            ->test('business.create-user')
            ->call('assignData', $business)
            ->set('form.user_id', $member->id)
            ->set('form.role', BusinessRole::Accountant->value)
            ->call('save')
            ->assertHasNoErrors();

        $membership = BusinessUser::query()
            ->where('business_id', $business->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame(BusinessRole::Accountant, $membership->role);

        Livewire::actingAs($admin)
            ->test('business.remove-user')
            ->call('assignData', $membership)
            ->call('remove')
            ->assertHasNoErrors();

        $this->assertSoftDeleted($membership);
    }

    public function test_cannot_remove_business_owner_membership(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        $ownerMembership = BusinessUser::query()
            ->where('business_id', $business->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        Livewire::actingAs($admin)
            ->test('business.remove-user')
            ->call('assignData', $ownerMembership)
            ->call('remove')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_user', [
            'id' => $ownerMembership->id,
            'deleted_at' => null,
            'role' => BusinessRole::Owner->value,
        ]);
    }

    public function test_business_users_page_renders(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.business.users', ['business' => $business])
            ->assertOk()
            ->assertSee($owner->mobile);
    }

    public function test_admin_can_create_owner_from_modal(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('business.create')
            ->set('newOwnerMobile', '09129876543')
            ->call('createOwner')
            ->assertHasNoErrors()
            ->assertSet('form.owner_id', User::query()->where('mobile', '09129876543')->value('id'));

        $this->assertDatabaseHas('users', [
            'mobile' => '09129876543',
        ]);
    }

    public function test_admin_can_create_owner_from_edit_modal(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        Livewire::actingAs($admin)
            ->test('business.edit')
            ->call('assignData', $business)
            ->set('newOwnerMobile', '09121112233')
            ->call('createOwner')
            ->assertHasNoErrors()
            ->assertSet('form.owner_id', User::query()->where('mobile', '09121112233')->value('id'));

        $this->assertDatabaseHas('users', [
            'mobile' => '09121112233',
        ]);
    }

    public function test_delete_business_requires_matching_name_confirmation(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create([
            'name' => 'Target Business',
        ]);

        Livewire::actingAs($admin)
            ->test('business.delete')
            ->call('assignData', $business)
            ->set('confirmationName', 'Wrong Name')
            ->call('delete')
            ->assertHasErrors(['confirmationName']);

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'deleted_at' => null,
        ]);

        Livewire::actingAs($admin)
            ->test('business.delete')
            ->call('assignData', $business)
            ->set('confirmationName', 'Target Business')
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted($business);
    }
}
