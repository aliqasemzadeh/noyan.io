<?php

namespace Tests\Feature;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Actions\Ledger\CreateJournalsFromSelectedTransactionsAction;
use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\TransactionType;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use App\Services\Accounting\BusinessLedgerSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateJournalsFromSelectedTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_journal_for_orphan_expense_transaction(): void
    {
        [$user, $business, $account, $expenseCategory] = $this->seedLedgerContext();

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'category_id' => $expenseCategory->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '250000',
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'skip_auto_post' => true,
        ]);

        $this->assertNull(
            JournalEntry::query()
                ->where('referenceable_type', $transaction->getMorphClass())
                ->where('referenceable_id', $transaction->id)
                ->first()
        );

        $result = app(CreateJournalsFromSelectedTransactionsAction::class)->handle($user, [$transaction->id]);

        $this->assertSame('independent', $result['mode']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['failed']);

        $entry = JournalEntry::query()
            ->where('referenceable_type', $transaction->getMorphClass())
            ->where('referenceable_id', $transaction->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertTrue($entry->isPosted());
    }

    public function test_merges_matching_expense_and_income_into_transfer_journal(): void
    {
        [$user, $business, $fromAccount, $expenseCategory] = $this->seedLedgerContext();

        $currency = Currency::query()->where('code', 'IRR')->firstOrFail();
        $toAccount = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'Destination Bank',
            'opening_balance' => '0',
            'current_balance' => '0',
        ]);
        $toAccount->syncFloatingDetail();

        $salesGl = GlAccount::findBySystemKey($business, 'sales');
        $incomeCategory = Category::factory()->system()->create([
            'type' => CategoryType::Income,
            'code' => 'income-leaf-test',
            'slug' => 'income-leaf-test-'.$business->id,
            'name' => 'Income Leaf',
            'gl_account_id' => $salesGl?->id,
            'is_system' => true,
        ]);

        $expense = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Expense,
            'account_id' => $fromAccount->id,
            'category_id' => $expenseCategory->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '50000000',
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'note' => 'Paya out',
            'skip_auto_post' => true,
        ]);

        $income = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $toAccount->id,
            'category_id' => $incomeCategory->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '50000000',
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'note' => 'Paya in',
            'skip_auto_post' => true,
        ]);

        $result = app(CreateJournalsFromSelectedTransactionsAction::class)->handle($user, [
            $expense->id,
            $income->id,
        ]);

        $this->assertSame('transfer', $result['mode']);
        $this->assertSame(1, $result['created']);
        $this->assertNotNull($result['transfer']);

        $this->assertSoftDeleted('transactions', ['id' => $expense->id]);
        $this->assertSoftDeleted('transactions', ['id' => $income->id]);

        $transfer = Transaction::query()->findOrFail($result['transfer']['transfer_id']);
        $this->assertSame(TransactionType::Transfer, $transfer->type);
        $this->assertSame((int) $fromAccount->id, (int) $transfer->account_id);
        $this->assertSame((int) $toAccount->id, (int) $transfer->destination_account_id);
        $this->assertSame(['merged_from_transaction_ids' => [$expense->id, $income->id]], $transfer->meta);

        $entry = JournalEntry::query()
            ->where('referenceable_type', $transfer->getMorphClass())
            ->where('referenceable_id', $transfer->id)
            ->with('lines')
            ->first();

        $this->assertNotNull($entry);
        $this->assertCount(2, $entry->lines);
        $this->assertTrue(
            $entry->lines->contains(fn ($line) => (int) $line->account_id === (int) $fromAccount->id)
        );
        $this->assertTrue(
            $entry->lines->contains(fn ($line) => (int) $line->account_id === (int) $toAccount->id)
        );
    }

    public function test_transaction_index_bulk_create_journal_action(): void
    {
        [$user, , $account, $expenseCategory] = $this->seedLedgerContext();

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'category_id' => $expenseCategory->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '120000',
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'skip_auto_post' => true,
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.transaction.index')
            ->set('selected', [(string) $transaction->id])
            ->call('openBulkCreateJournalModal')
            ->assertSet('bulkJournalPreview.mode', 'independent')
            ->call('applyBulkCreateJournal')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('journal_entries', [
            'referenceable_type' => $transaction->getMorphClass(),
            'referenceable_id' => $transaction->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Business, 2: Account, 3: Category}
     */
    protected function seedLedgerContext(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        app(BusinessLedgerSettingsService::class)->update($business, [
            'auto_post_mode' => AutoPostMode::Off,
        ]);

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
            'name' => 'Source Bank',
            'opening_balance' => '100000000',
            'current_balance' => '100000000',
        ]);
        $account->syncFloatingDetail();

        $bankFeesGl = GlAccount::findBySystemKey($business, 'bank_fees')
            ?? GlAccount::findBySystemKey($business, 'operating_expenses');

        $expenseCategory = Category::factory()->system()->create([
            'type' => CategoryType::Expense,
            'code' => 'expense-leaf-test',
            'slug' => 'expense-leaf-test-'.$business->id,
            'name' => 'Expense Leaf',
            'gl_account_id' => $bankFeesGl?->id,
            'is_system' => true,
        ]);

        return [$user, $business, $account, $expenseCategory];
    }
}
