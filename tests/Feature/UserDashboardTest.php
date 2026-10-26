<?php

namespace Tests\Feature;

use App\Enums\Support\TicketStatus;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summarizes_business_panels_and_tickets(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'رضایی',
        ]);
        $business = Business::factory()->for($user, 'owner')->create(['name' => 'نوین تجارت']);

        Ticket::factory()->for($user)->for($business)->create([
            'title' => 'قطعی چاپگر',
            'status' => TicketStatus::WaitingUser,
        ]);
        Ticket::factory()->for($user)->create([
            'title' => 'سوال صورتحساب',
            'status' => TicketStatus::New,
        ]);
        Ticket::factory()->for($user)->closed()->create([
            'title' => 'تیکت بسته‌شده',
        ]);
        Ticket::factory()->create([
            'title' => 'تیکت شخص دیگر',
            'status' => TicketStatus::WaitingUser,
        ]);

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee(__('general.dashboard'))
            ->assertSee('علی رضایی')
            ->assertSee('نوین تجارت')
            ->assertSee(__('general.open_tickets'))
            ->assertSee(__('general.tickets_waiting_for_you'))
            ->assertSee('قطعی چاپگر')
            ->assertSee('سوال صورتحساب')
            ->assertSee('تیکت بسته‌شده')
            ->assertDontSee('تیکت شخص دیگر')
            ->assertDontSee(__('general.user_profile_form_hint'))
            ->assertSee(route('accounting.dashboard'), false)
            ->assertSee(route('warehouse.dashboard'), false)
            ->assertSee(route('sale.dashboard'), false);

        $component = Livewire::actingAs($user)->test('pages::panel.user.dashboard.index');

        $this->assertSame(2, $component->instance()->ticketSummary()['open']);
        $this->assertSame(1, $component->instance()->ticketSummary()['waiting']);
    }

    public function test_seller_only_sees_the_sale_panel_shortcut(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $seller = User::factory()->create(['current_business_id' => $business->id]);

        BusinessUser::factory()->seller()->create([
            'business_id' => $business->id,
            'user_id' => $seller->id,
        ]);

        $this->actingAs($seller)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee(route('sale.dashboard'), false)
            ->assertDontSee(route('accounting.dashboard'), false)
            ->assertDontSee(route('warehouse.dashboard'), false);
    }

    public function test_user_can_switch_business_from_the_dashboard(): void
    {
        $user = User::factory()->create();
        $first = Business::factory()->for($user, 'owner')->create(['name' => 'کسب‌وکار اول']);
        $second = Business::factory()->create(['name' => 'کسب‌وکار دوم']);

        BusinessUser::factory()->admin()->create([
            'business_id' => $second->id,
            'user_id' => $user->id,
        ]);

        $user->forceFill(['current_business_id' => $first->id])->save();

        Livewire::actingAs($user)
            ->test('pages::panel.user.dashboard.index')
            ->assertSee('کسب‌وکار اول')
            ->assertSee('کسب‌وکار دوم')
            ->call('switchTo', $second->id)
            ->assertHasNoErrors()
            ->assertRedirect(route('user.dashboard'));

        $this->assertSame($second->id, $user->fresh()->current_business_id);
    }

    public function test_user_cannot_switch_to_a_foreign_business_from_the_dashboard(): void
    {
        $user = User::factory()->create();
        $own = Business::factory()->for($user, 'owner')->create();
        $foreign = Business::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::panel.user.dashboard.index')
            ->call('switchTo', $foreign->id)
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertSame($own->id, $user->fresh()->current_business_id);
    }
}
