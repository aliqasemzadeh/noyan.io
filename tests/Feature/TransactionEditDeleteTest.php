<?php

namespace Tests\Feature;

use App\Actions\Loans\CreateLoanAction;
use App\Actions\Loans\RecordLoanPaymentAction;
use App\Actions\Transactions\DeleteTransactionAction;
use App\Actions\Transactions\ProcessTransactionAction;
use App\Actions\Transactions\UpdateTransactionAction;
use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\LoanType;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_income_reverses_and_reapplies_balances(): void
    {
        [$user, $account, $party] = $this->prepareContext(openingBalance: '10000', partyBalance: '5000');

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '1000',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $account->refresh();
        $party->refresh();
        $this->assertSame(0, bccomp((string) $account->current_balance, '11000', 18));
        $this->assertSame(0, bccomp((string) $party->balance, '4000', 18));

        app(UpdateTransactionAction::class)->handle($user, $transaction, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '2500',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $account->refresh();
        $party->refresh();
        $this->assertSame(0, bccomp((string) $account->current_balance, '12500', 18));
        $this->assertSame(0, bccomp((string) $party->balance, '2500', 18));
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => '2500',
        ]);
    }

    public function test_soft_deleting_transaction_restores_balances(): void
    {
        [$user, $account, $party] = $this->prepareContext(openingBalance: '10000', partyBalance: '5000');

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '1500',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        app(DeleteTransactionAction::class)->handle($user, $transaction);

        $account->refresh();
        $party->refresh();
        $this->assertSame(0, bccomp((string) $account->current_balance, '10000', 18));
        $this->assertSame(0, bccomp((string) $party->balance, '5000', 18));
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
        $this->assertNull(Transaction::query()->find($transaction->id));
    }

    public function test_updating_invoice_linked_transaction_adjusts_paid_amount(): void
    {
        [$user, $account, $party] = $this->prepareContext(openingBalance: '10000', partyBalance: '0');

        $invoice = Invoice::factory()->finalized()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'party_id' => $party->id,
            'total_amount' => '5000',
            'paid_amount' => '0',
            'payment_status' => InvoicePaymentStatus::Unpaid,
        ]);

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'invoice_id' => $invoice->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '2000',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $invoice->refresh();
        $this->assertSame(0, bccomp((string) $invoice->paid_amount, '2000', 18));
        $this->assertSame(InvoicePaymentStatus::PartiallyPaid, $invoice->payment_status);

        app(UpdateTransactionAction::class)->handle($user, $transaction, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '5000',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $invoice->refresh();
        $this->assertSame(0, bccomp((string) $invoice->paid_amount, '5000', 18));
        $this->assertSame(InvoicePaymentStatus::Paid, $invoice->payment_status);
        $this->assertSame($invoice->id, $transaction->fresh()->invoice_id);
    }

    public function test_updating_loan_payment_adjusts_loan_paid_amount(): void
    {
        [$user, $account, $party, $loan] = $this->prepareLoan(LoanType::Received, '3000');

        app(RecordLoanPaymentAction::class)->handle($user, $loan, [
            'amount' => '1000',
            'transaction_date' => now()->toDateString(),
            'account_id' => $account->id,
        ]);

        $payment = Transaction::query()
            ->where('loan_id', $loan->id)
            ->where('meta->loan_payment', true)
            ->firstOrFail();

        app(UpdateTransactionAction::class)->handle($user, $payment, [
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '1500',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $loan->refresh();
        $this->assertSame(0, bccomp((string) $loan->paid_amount, '1500', 18));
    }

    public function test_skip_party_balance_delete_does_not_touch_party(): void
    {
        [$user, $account, $party] = $this->prepareContext(openingBalance: '10000', partyBalance: '3000');

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '800',
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'skip_party_balance' => true,
        ]);

        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '3000', 18));
        $this->assertSame(0, bccomp((string) $account->fresh()->current_balance, '10800', 18));

        app(DeleteTransactionAction::class)->handle($user, $transaction);

        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '3000', 18));
        $this->assertSame(0, bccomp((string) $account->fresh()->current_balance, '10000', 18));
    }

    public function test_livewire_can_edit_and_delete_transaction(): void
    {
        [$user, $account] = $this->prepareContext(openingBalance: '10000');

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '1200',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.transaction.edit')
            ->call('assignData', $transaction)
            ->set('form.amount', '1800')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => '1800',
        ]);

        $account->refresh();
        $this->assertSame(0, bccomp((string) $account->current_balance, '11800', 18));

        Livewire::actingAs($user)
            ->test('accounting.transaction.delete')
            ->call('assignData', $transaction->fresh())
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
        $account->refresh();
        $this->assertSame(0, bccomp((string) $account->current_balance, '10000', 18));
    }

    public function test_account_delete_is_soft(): void
    {
        [$user, $account] = $this->prepareContext(openingBalance: '10000');

        Livewire::actingAs($user)
            ->test('accounting.account.delete')
            ->call('assignData', $account)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('accounting_accounts', ['id' => $account->id]);
        $this->assertNull(Account::query()->find($account->id));
        $this->assertNotNull(Account::withTrashed()->find($account->id));
    }

    /**
     * @return array{0: User, 1: Account, 2?: Party}
     */
    protected function prepareContext(string $openingBalance = '10000', string $partyBalance = '0'): array
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
            'opening_balance' => $openingBalance,
            'is_active' => true,
        ]);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'balance' => $partyBalance,
            'is_active' => true,
        ]);

        return [$user, $account, $party];
    }

    /**
     * @return array{0: User, 1: Account, 2: Party, 3: Loan}
     */
    protected function prepareLoan(LoanType $type, string $principal): array
    {
        [$user, $account, $party] = $this->prepareContext(openingBalance: '5000');

        $loan = app(CreateLoanAction::class)->handle($user, [
            'type' => $type,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'title' => 'Test loan',
            'principal_amount' => $principal,
            'interest_amount' => '0',
            'issue_date' => now()->toDateString(),
        ]);

        $account->refresh();

        return [$user, $account, $party, $loan];
    }
}
