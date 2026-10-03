<?php

namespace Tests\Feature;

use App\Actions\Loans\CreateLoanAction;
use App\Enums\Accounting\LoanType;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_received_loan_credits_account_and_links_transaction(): void
    {
        [$user, $account, $party] = $this->prepareContext(openingBalance: '5000');

        $loan = app(CreateLoanAction::class)->handle($user, [
            'type' => LoanType::Received,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'title' => 'Bank loan',
            'principal_amount' => '2000',
            'interest_amount' => '200',
            'issue_date' => now()->toDateString(),
        ]);

        $this->assertSame(0, bccomp((string) $loan->total_amount, '2200', 18));
        $this->assertSame(0, bccomp((string) $loan->paid_amount, '0', 18));

        $account->refresh();
        $this->assertSame(0, bccomp((string) $account->current_balance, '7000', 18));

        $transaction = Transaction::query()->where('loan_id', $loan->id)->first();
        $this->assertNotNull($transaction);
        $this->assertSame(TransactionType::Income, $transaction->type);
        $this->assertSame(0, bccomp((string) $transaction->amount, '2000', 18));
        $this->assertSame($party->id, $transaction->party_id);
    }

    public function test_given_loan_debits_account(): void
    {
        [$user, $account, $party] = $this->prepareContext(openingBalance: '8000');

        app(CreateLoanAction::class)->handle($user, [
            'type' => LoanType::Given,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'title' => 'Staff advance',
            'principal_amount' => '1500',
            'interest_amount' => '0',
            'issue_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertSame(0, bccomp((string) $account->current_balance, '6500', 18));

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'party_id' => $party->id,
            'type' => TransactionType::Expense->value,
            'amount' => '1500',
        ]);
    }

    public function test_loans_index_is_reachable(): void
    {
        [$user] = $this->prepareContext();

        $this->actingAs($user)
            ->get(route('accounting.loans.index'))
            ->assertOk();
    }

    public function test_user_can_create_loan_from_livewire_form(): void
    {
        [$user, $account, $party] = $this->prepareContext();

        Livewire::actingAs($user)
            ->test('accounting.loan.create')
            ->set('form.type', LoanType::Received->value)
            ->set('form.party_id', $party->id)
            ->set('form.account_id', $account->id)
            ->set('form.title', 'Friend loan')
            ->set('form.principal_amount', '1000')
            ->set('form.interest_amount', '0')
            ->set('form.issue_date', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('loans', [
            'business_id' => $account->business_id,
            'party_id' => $party->id,
            'title' => 'Friend loan',
            'type' => LoanType::Received->value,
        ]);
    }

    public function test_loan_show_is_scoped_to_current_business(): void
    {
        [$user, $account, $party] = $this->prepareContext();
        $loan = Loan::factory()->create([
            'business_id' => $account->business_id,
            'party_id' => $party->id,
            'account_id' => $account->id,
        ]);

        $otherUser = User::factory()->create();
        $otherBusiness = Business::factory()->for($otherUser, 'owner')->create();
        $otherUser->forceFill(['current_business_id' => $otherBusiness->id])->save();

        $this->actingAs($otherUser)
            ->get(route('accounting.loans.show', $loan))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Account, 2: Party}
     */
    protected function prepareContext(string $openingBalance = '10000'): array
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
            'current_balance' => $openingBalance,
            'is_active' => true,
        ]);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'balance' => '0',
        ]);

        return [$user, $account, $party];
    }
}
