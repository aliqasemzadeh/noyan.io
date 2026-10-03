<?php

namespace Tests\Feature;

use App\Actions\Cheques\RegisterChequeAction;
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

class ChequeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cheques_index_is_reachable(): void
    {
        [$user] = $this->prepareContext();

        $this->actingAs($user)
            ->get(route('accounting.cheques.index'))
            ->assertOk();
    }

    public function test_index_is_scoped_to_current_business(): void
    {
        [$user, $account, $party] = $this->prepareContext();

        app(RegisterChequeAction::class)->handle($user, [
            'type' => ChequeType::Received,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'cheque_number' => 'mine-1',
            'bank_name' => 'ملی',
            'amount' => '1000',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $otherBusiness = Business::factory()->create();
        Cheque::factory()->create([
            'business_id' => $otherBusiness->id,
            'party_id' => Party::factory()->create(['business_id' => $otherBusiness->id])->id,
            'account_id' => Account::factory()->create(['business_id' => $otherBusiness->id])->id,
            'cheque_number' => 'other-1',
            'bank_name' => 'ملت',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.cheque.index')
            ->assertSee('mine-1')
            ->assertDontSee('other-1');
    }

    public function test_user_can_create_cheque_from_livewire_form(): void
    {
        [$user, $account, $party] = $this->prepareContext();

        Livewire::actingAs($user)
            ->test('accounting.cheque.create')
            ->set('form.type', ChequeType::Received->value)
            ->set('form.party_id', $party->id)
            ->set('form.account_id', $account->id)
            ->set('form.cheque_number', 'LW-100')
            ->set('form.bank_name', 'ملی')
            ->set('form.amount', '2500')
            ->set('form.issue_date', now()->toDateString())
            ->set('form.due_date', now()->addDays(7)->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cheques', [
            'cheque_number' => 'LW-100',
            'type' => ChequeType::Received->value,
            'status' => ChequeStatus::Registered->value,
            'party_id' => $party->id,
        ]);
    }

    public function test_user_can_clear_cheque_from_change_status_component(): void
    {
        [$user, $account, $party] = $this->prepareContext(partyBalance: '5000', accountBalance: '10000');

        $cheque = app(RegisterChequeAction::class)->handle($user, [
            'type' => ChequeType::Received,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'cheque_number' => 'CLR-1',
            'bank_name' => 'ملی',
            'amount' => '1200',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test('accounting.cheque.change-status')
            ->call('assignData', $cheque->id)
            ->set('form.status', ChequeStatus::Cleared->value)
            ->set('form.transaction_date', now()->toDateString())
            ->set('form.account_id', $account->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(ChequeStatus::Cleared, $cheque->fresh()->status);
        $this->assertDatabaseHas('transactions', [
            'cheque_id' => $cheque->id,
            'type' => 'income',
            'amount' => '1200',
        ]);
    }

    /**
     * @return array{0: User, 1: Account, 2: Party}
     */
    protected function prepareContext(string $partyBalance = '0', string $accountBalance = '5000'): array
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
            'current_balance' => $accountBalance,
            'is_active' => true,
        ]);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'balance' => $partyBalance,
            'is_active' => true,
            'name' => 'Customer One',
        ]);

        return [$user, $account, $party];
    }
}
