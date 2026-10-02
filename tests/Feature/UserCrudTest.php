<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_via_create_component(): void
    {
        $viewer = User::factory()->create();

        Livewire::actingAs($viewer)
            ->test('user.create')
            ->set('form.mobile', '09123456789')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('panels.administrator.user.index.table');

        $this->assertDatabaseHas('users', [
            'mobile' => '09123456789',
        ]);
    }

    public function test_create_user_validates_mobile_format(): void
    {
        $viewer = User::factory()->create();

        Livewire::actingAs($viewer)
            ->test('user.create')
            ->set('form.mobile', '123456')
            ->call('save')
            ->assertHasErrors(['form.mobile']);
    }

    public function test_create_user_validates_unique_mobile(): void
    {
        $viewer = User::factory()->create();
        User::factory()->create(['mobile' => '09123456789']);

        Livewire::actingAs($viewer)
            ->test('user.create')
            ->set('form.mobile', '09123456789')
            ->call('save')
            ->assertHasErrors(['form.mobile']);
    }

    public function test_user_can_be_edited_via_edit_component(): void
    {
        $viewer = User::factory()->create();
        $targetUser = User::factory()->create(['mobile' => '09121111111']);

        Livewire::actingAs($viewer)
            ->test('user.edit')
            ->dispatch('panels.administrator.user.edit.assign-data', user: $targetUser)
            ->assertSet('form.mobile', '09121111111')
            ->set('form.mobile', '09122222222')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('panels.administrator.user.index.table');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'mobile' => '09122222222',
        ]);
    }

    public function test_edit_user_allows_keeping_same_mobile(): void
    {
        $viewer = User::factory()->create();
        $targetUser = User::factory()->create(['mobile' => '09121111111']);

        Livewire::actingAs($viewer)
            ->test('user.edit')
            ->dispatch('panels.administrator.user.edit.assign-data', user: $targetUser)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('panels.administrator.user.index.table');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'mobile' => '09121111111',
        ]);
    }

    public function test_edit_user_validates_unique_mobile_against_other_users(): void
    {
        $viewer = User::factory()->create();
        $userA = User::factory()->create(['mobile' => '09121111111']);
        User::factory()->create(['mobile' => '09122222222']);

        Livewire::actingAs($viewer)
            ->test('user.edit')
            ->dispatch('panels.administrator.user.edit.assign-data', user: $userA)
            ->set('form.mobile', '09122222222')
            ->call('save')
            ->assertHasErrors(['form.mobile']);
    }

    public function test_user_can_be_deleted_via_delete_component(): void
    {
        $viewer = User::factory()->create();
        $targetUser = User::factory()->create(['mobile' => '09123333333']);

        Livewire::actingAs($viewer)
            ->test('user.delete')
            ->dispatch('panels.administrator.user.delete.assign-data', user: $targetUser)
            ->call('delete')
            ->assertHasNoErrors()
            ->assertDispatched('panels.administrator.user.index.table');

        $this->assertSoftDeleted('users', [
            'id' => $targetUser->id,
        ]);
    }

    public function test_users_index_page_contains_crud_elements(): void
    {
        $viewer = User::factory()->create(['mobile' => '09121111111']);
        $listed = User::factory()->create(['mobile' => '09171234567']);

        $this->actingAs($viewer)
            ->get(route('system.users.index'))
            ->assertOk()
            ->assertSee(__('general.users'))
            ->assertSee(__('general.create_user'))
            ->assertSee(__('general.actions'))
            ->assertSee($listed->mobile)
            ->assertSeeLivewire('user.create')
            ->assertSeeLivewire('user.edit')
            ->assertSeeLivewire('user.delete');
    }
}
