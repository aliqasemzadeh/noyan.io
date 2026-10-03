<?php

namespace Tests\Feature;

use App\Actions\Loans\CreateLoanAction;
use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\LoanType;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use App\Support\LocaleDate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

class PartyAccountLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_party_view_shows_transactions_invoices_and_loans(): void
    {
        [$user, $account, $party] = $this->prepareContext();

        app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '500',
            'note' => 'Party payment',
        ]);

        $invoice = Invoice::factory()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'party_id' => $party->id,
            'party_name' => $party->name,
            'invoice_number' => 'SL-LEDGER-1',
            'total_amount' => '2500',
        ]);

        $loan = app(CreateLoanAction::class)->handle($user, [
            'type' => LoanType::Received,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'title' => 'Party loan',
            'principal_amount' => '1000',
            'interest_amount' => '0',
            'issue_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.party.view', ['party' => $party])
            ->assertSee('Party payment')
            ->set('ledgerTab', 'invoices')
            ->assertSee($invoice->invoice_number)
            ->set('ledgerTab', 'loans')
            ->assertSee($loan->title);
    }

    public function test_account_view_shows_incoming_and_outgoing_transactions(): void
    {
        [$user, $account, $party] = $this->prepareContext();

        app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '700',
            'note' => 'Incoming cash',
        ]);

        app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '200',
            'note' => 'Outgoing cash',
        ]);

        $account->refresh();

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.account.view', ['account' => $account])
            ->assertSee('Incoming cash')
            ->assertSee('Outgoing cash')
            ->assertSee(__('general.current_balance'));
    }

    public function test_locale_date_parses_jalali_and_gregorian(): void
    {
        App::setLocale('fa');
        $jalali = LocaleDate::parseFilterDate('1403/01/15');
        $this->assertInstanceOf(Carbon::class, $jalali);
        $this->assertSame(
            Jalalian::fromFormat('Y/m/d', '1403/01/15')->toCarbon()->toDateString(),
            $jalali->toDateString()
        );

        App::setLocale('en');
        $gregorian = LocaleDate::parseFilterDate('2024-04-03');
        $this->assertInstanceOf(Carbon::class, $gregorian);
        $this->assertSame('2024-04-03', $gregorian->toDateString());
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
            'current_balance' => '10000',
            'is_active' => true,
        ]);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'name' => 'Ledger Party',
        ]);

        return [$user, $account, $party];
    }
}
