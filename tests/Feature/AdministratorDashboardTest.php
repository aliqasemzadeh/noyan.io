<?php

namespace Tests\Feature;

use App\Enums\Accounting\TransactionType;
use App\Enums\Support\TicketStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class AdministratorDashboardTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_administrator_dashboard_shows_core_kpis(): void
    {
        Cache::flush();

        $admin = $this->grantAdministratorAccess(User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]));

        $owner = User::factory()->create([
            'first_name' => 'Sara',
            'last_name' => 'Ahmadi',
            'mobile' => '09121234567',
        ]);

        $business = Business::factory()->for($owner, 'owner')->create([
            'name' => 'Noyan Shop',
            'is_active' => true,
        ]);

        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
        ]);
        $business->activateCurrency($currency, '1');

        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
        ]);

        Transaction::factory()->create([
            'business_id' => $business->id,
            'created_by' => $owner->id,
            'account_id' => $account->id,
            'type' => TransactionType::Income,
            'transaction_date' => now()->toDateString(),
            'currency' => 'IRR',
            'amount' => '1500000',
            'base_amount' => '1500000',
        ]);

        Transaction::factory()->expense()->create([
            'business_id' => $business->id,
            'created_by' => $owner->id,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
            'currency' => 'IRR',
            'amount' => '500000',
            'base_amount' => '500000',
        ]);

        Transaction::factory()->transfer()->create([
            'business_id' => $business->id,
            'created_by' => $owner->id,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
            'currency' => 'IRR',
            'amount' => '100000',
            'base_amount' => '100000',
        ]);

        Ticket::factory()->create([
            'user_id' => $owner->id,
            'status' => TicketStatus::New,
        ]);

        Ticket::factory()->closed()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->get(route('system.dashboard'))
            ->assertOk()
            ->assertSee(__('general.total_members'))
            ->assertSee(__('general.total_businesses'))
            ->assertSee(__('general.daily_transaction_volume'))
            ->assertSee(__('general.open_tickets'))
            ->assertSee(__('general.today_income'))
            ->assertSee(__('general.today_expense'))
            ->assertSee('Noyan Shop')
            ->assertSee('09121234567')
            ->assertSee(number_format(2))
            ->assertSee(number_format(1))
            ->assertSee(number_format(2000000))
            ->assertSee(number_format(1500000))
            ->assertSee(number_format(500000));
    }

    public function test_guest_cannot_view_administrator_dashboard(): void
    {
        $this->get(route('system.dashboard'))
            ->assertRedirect(route('login'));
    }
}
