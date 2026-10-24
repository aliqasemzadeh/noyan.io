<?php

namespace Tests\Feature;

use App\Enums\Support\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketFile;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_user_can_create_ticket_without_business(): void
    {
        $user = User::factory()->create();
        $category = TicketCategory::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)
            ->test('pages::panel.user.support.ticket.create')
            ->set('form.title', 'Need help')
            ->set('form.ticket_category_id', $category->id)
            ->set('form.body', 'Please help me with this issue.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'user_id' => $user->id,
            'title' => 'Need help',
            'business_id' => null,
            'status' => TicketStatus::New->value,
        ]);

        $this->assertDatabaseHas('ticket_replies', [
            'user_id' => $user->id,
            'body' => 'Please help me with this issue.',
        ]);
    }

    public function test_user_cannot_view_another_users_ticket(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('user.support.tickets.view', $ticket))
            ->assertForbidden();
    }

    public function test_user_cannot_reply_to_closed_ticket(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->closed()->create(['user_id' => $user->id]);
        TicketReply::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => 'Initial',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.user.support.ticket.view', ['ticket' => $ticket])
            ->set('body', 'Trying to reply')
            ->call('reply')
            ->assertHasErrors(['body']);

        $this->assertDatabaseMissing('ticket_replies', [
            'ticket_id' => $ticket->id,
            'body' => 'Trying to reply',
        ]);
    }

    public function test_admin_can_reply_and_mark_waiting_user(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $ticket = Ticket::factory()->waitingStaff()->create();

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.support.ticket.view', ['ticket' => $ticket])
            ->set('body', 'Staff response')
            ->call('reply')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'body' => 'Staff response',
        ]);

        $this->assertSame(TicketStatus::WaitingUser, $ticket->fresh()->status);
    }

    public function test_admin_can_close_and_reopen_ticket(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $ticket = Ticket::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.support.ticket.view', ['ticket' => $ticket])
            ->call('closeTicket')
            ->assertHasNoErrors();

        $this->assertTrue($ticket->fresh()->isClosed());

        Livewire::actingAs($admin)
            ->test('pages::panel.administrator.support.ticket.view', ['ticket' => $ticket->fresh()])
            ->call('reopenTicket')
            ->assertHasNoErrors();

        $this->assertSame(TicketStatus::WaitingStaff, $ticket->fresh()->status);
    }

    public function test_user_can_close_own_ticket(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('pages::panel.user.support.ticket.view', ['ticket' => $ticket])
            ->call('closeTicket')
            ->assertHasNoErrors();

        $this->assertTrue($ticket->fresh()->isClosed());
    }

    public function test_unauthorized_user_cannot_view_ticket_file(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $owner->id]);

        $path = 'tickets/'.$ticket->id.'/secret.jpg';
        Storage::disk('local')->put($path, 'fake-image');

        $file = TicketFile::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'path' => $path,
            'original_name' => 'secret.jpg',
            'mime' => 'image/jpeg',
        ]);

        $this->actingAs($other)
            ->get(route('support.ticket-files.show', $file))
            ->assertForbidden();
    }

    public function test_owner_can_view_ticket_file_inline(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $owner->id]);

        $path = 'tickets/'.$ticket->id.'/photo.jpg';
        Storage::disk('local')->put($path, UploadedFile::fake()->image('photo.jpg')->get());

        $file = TicketFile::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'path' => $path,
            'original_name' => 'photo.jpg',
            'mime' => 'image/jpeg',
        ]);

        $this->actingAs($owner)
            ->get(route('support.ticket-files.show', $file))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_user_tickets_index_lists_only_own_tickets(): void
    {
        $user = User::factory()->create();
        $own = Ticket::factory()->create([
            'user_id' => $user->id,
            'title' => 'My Ticket Title',
        ]);
        Ticket::factory()->create(['title' => 'Someone Else Ticket']);

        $this->actingAs($user)
            ->get(route('user.support.tickets.index'))
            ->assertOk()
            ->assertSee('My Ticket Title')
            ->assertDontSee('Someone Else Ticket');

        $this->assertTrue($own->exists);
    }

    public function test_admin_tickets_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('system.support.tickets.index'))
            ->assertForbidden();
    }
}
