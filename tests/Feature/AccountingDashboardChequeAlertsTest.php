<?php

namespace Tests\Feature;

use App\Enums\Accounting\ChequeStatus;
use App\Enums\Accounting\ChequeType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Cheque;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingDashboardChequeAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_overdue_and_upcoming_cheques(): void
    {
        [$user, $account, $party] = $this->prepareContext();

        Cheque::factory()->create([
            'business_id' => $user->current_business_id,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'created_by' => $user->id,
            'type' => ChequeType::Received,
            'status' => ChequeStatus::Registered,
            'cheque_number' => 'OVERDUE-1',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        Cheque::factory()->create([
            'business_id' => $user->current_business_id,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'created_by' => $user->id,
            'type' => ChequeType::Issued,
            'status' => ChequeStatus::Registered,
            'cheque_number' => 'SOON-1',
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        Cheque::factory()->create([
            'business_id' => $user->current_business_id,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'created_by' => $user->id,
            'type' => ChequeType::Received,
            'status' => ChequeStatus::Cleared,
            'cheque_number' => 'CLEARED-1',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.dashboard.index')
            ->assertSee('OVERDUE-1')
            ->assertSee('SOON-1')
            ->assertDontSee('CLEARED-1');
    }

    public function test_dashboard_hides_alert_block_when_empty(): void
    {
        [$user] = $this->prepareContext();

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.dashboard.index')
            ->assertDontSee(__('general.cheque_alerts'));
    }

    public function test_dashboard_shows_accounting_ai_intro(): void
    {
        [$user] = $this->prepareContext();

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.dashboard.index')
            ->assertSee(__('general.accounting_ai_title'))
            ->assertSee(__('general.accounting_ai_description'))
            ->assertSee(__('general.accounting_ai_footer'));
    }

    /**
     * @return array{0: User, 1: Account, 2: Party}
     */
    protected function prepareContext(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
            'is_system' => true,
            'business_id' => null,
        ]);
        $business->activateCurrency($currency, '1', true);

        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'is_active' => true,
        ]);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'name' => 'Alert Party',
        ]);

        return [$user, $account, $party];
    }
}
