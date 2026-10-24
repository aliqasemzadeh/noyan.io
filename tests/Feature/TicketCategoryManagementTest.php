<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class TicketCategoryManagementTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_admin_can_create_ticket_category(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($admin)
            ->test('support.ticket-category.create')
            ->set('form.title', 'Billing')
            ->set('form.sort_order', 1)
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_categories', [
            'title' => 'Billing',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_ticket_category(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $category = TicketCategory::factory()->create(['title' => 'Old']);

        Livewire::actingAs($admin)
            ->test('support.ticket-category.edit')
            ->call('assignData', $category)
            ->set('form.title', 'Updated')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_categories', [
            'id' => $category->id,
            'title' => 'Updated',
        ]);
    }

    public function test_admin_cannot_delete_ticket_category_in_use(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $category = TicketCategory::factory()->create();
        Ticket::factory()->create(['ticket_category_id' => $category->id]);

        Livewire::actingAs($admin)
            ->test('support.ticket-category.delete')
            ->call('assignData', $category)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_categories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_soft_delete_unused_ticket_category(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $category = TicketCategory::factory()->create();

        Livewire::actingAs($admin)
            ->test('support.ticket-category.delete')
            ->call('assignData', $category)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted($category);
    }

    public function test_ticket_categories_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('system.support.ticket-categories.index'))
            ->assertForbidden();
    }

    public function test_ticket_categories_index_is_accessible_for_admin(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        TicketCategory::factory()->create(['title' => 'Support Category']);

        $this->actingAs($admin)
            ->get(route('system.support.ticket-categories.index'))
            ->assertOk()
            ->assertSee('Support Category');
    }
}
