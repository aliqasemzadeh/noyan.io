<?php

namespace Tests\Feature;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Actions\Transactions\DeleteTransactionAction;
use App\Actions\Transactions\ProcessTransactionAction;
use App\Actions\Transactions\UpdateTransactionAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\TransactionType;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use App\Services\Accounting\BusinessLedgerSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryAutoPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_realtime_auto_post_creates_posted_journal_entry(): void
    {
        [$user, $business, $account, $category] = $this->prepareTreasuryContext(
            CategoryType::Income,
            'sales',
            AutoPostMode::Realtime,
        );

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '1500',
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'reference_number' => 'IN-1',
        ]);

        $entry = JournalEntry::query()
            ->where('referenceable_type', $transaction->getMorphClass())
            ->where('referenceable_id', $transaction->id)
            ->with('lines')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(JournalEntryStatus::Posted, $entry->status);
        $this->assertCount(2, $entry->lines);

        $settings = app(BusinessLedgerSettingsService::class)->get($business);
        $cashLine = $entry->lines->firstWhere('gl_account_id', $settings->default_cash_and_bank_id);
        $incomeLine = $entry->lines->firstWhere('gl_account_id', $category->gl_account_id);

        $this->assertNotNull($cashLine);
        $this->assertNotNull($incomeLine);
        $this->assertSame(0, bccomp((string) $cashLine->debit, '1500', 4));
        $this->assertSame($account->id, $cashLine->account_id);
        $this->assertSame(0, bccomp((string) $incomeLine->credit, '1500', 4));
        $this->assertSame(0, bccomp((string) $account->fresh()->current_balance, '11500', 18));
    }

    public function test_expense_manual_review_creates_draft_journal_entry(): void
    {
        [$user, , $account, $category] = $this->prepareTreasuryContext(
            CategoryType::Expense,
            'rent_expense',
            AutoPostMode::ManualReview,
        );

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '400',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $entry = JournalEntry::query()
            ->where('referenceable_id', $transaction->id)
            ->where('referenceable_type', $transaction->getMorphClass())
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(JournalEntryStatus::Draft, $entry->status);
    }

    public function test_transfer_auto_posts_source_and_destination_cash_details(): void
    {
        [$user, $business, $source] = $this->prepareTreasuryContext(
            CategoryType::Income,
            'sales',
            AutoPostMode::Realtime,
        );

        $destination = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $source->currency_id,
            'current_balance' => '0',
        ]);
        $destination->syncFloatingDetail();

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Transfer,
            'account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '250',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $entry = JournalEntry::query()
            ->where('referenceable_id', $transaction->id)
            ->where('referenceable_type', $transaction->getMorphClass())
            ->with('lines')
            ->first();

        $this->assertNotNull($entry);
        $this->assertCount(2, $entry->lines);
        $this->assertNotNull($entry->lines->firstWhere('account_id', $source->id));
        $this->assertNotNull($entry->lines->firstWhere('account_id', $destination->id));
    }

    public function test_income_with_invoice_posts_cash_versus_receivable(): void
    {
        [$user, $business, $account] = $this->prepareTreasuryContext(
            CategoryType::Income,
            'sales',
            AutoPostMode::Realtime,
        );

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_customer' => true,
            'balance' => '5000',
        ]);

        $invoice = Invoice::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'party_id' => $party->id,
            'type' => InvoiceType::Sale,
            'total_amount' => '5000',
            'paid_amount' => '0',
            'finalized_at' => now(),
        ]);

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'invoice_id' => $invoice->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '1000',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $settings = app(BusinessLedgerSettingsService::class)->get($business);
        $entry = JournalEntry::query()
            ->where('referenceable_id', $transaction->id)
            ->with('lines')
            ->first();

        $this->assertNotNull($entry);
        $arLine = $entry->lines->firstWhere('gl_account_id', $settings->default_accounts_receivable_id);
        $this->assertNotNull($arLine);
        $this->assertSame($party->id, $arLine->party_id);
        $this->assertSame(0, bccomp((string) $arLine->credit, '1000', 4));
    }

    public function test_update_replaces_linked_journal_entry(): void
    {
        [$user, , $account, $category] = $this->prepareTreasuryContext(
            CategoryType::Income,
            'sales',
            AutoPostMode::ManualReview,
        );

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '100',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $firstEntryId = JournalEntry::query()
            ->where('referenceable_id', $transaction->id)
            ->value('id');

        app(UpdateTransactionAction::class)->handle($user, $transaction, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '175',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $active = JournalEntry::query()
            ->where('referenceable_id', $transaction->id)
            ->where('voucher_number', 'not like', '%__del_%')
            ->with('lines')
            ->get();

        $this->assertCount(1, $active);
        $this->assertNotSame($firstEntryId, $active->first()->id);
        $cashDebit = $active->first()->lines->firstWhere('account_id', $account->id)?->debit;
        $this->assertSame(0, bccomp((string) $cashDebit, '175', 4));
    }

    public function test_delete_voids_linked_journal_entry(): void
    {
        [$user, , $account, $category] = $this->prepareTreasuryContext(
            CategoryType::Expense,
            'bank_fees',
            AutoPostMode::Realtime,
        );

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '50',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $this->assertSame(1, JournalEntry::query()->where('referenceable_id', $transaction->id)->count());

        app(DeleteTransactionAction::class)->handle($user, $transaction);

        $this->assertSame(
            0,
            JournalEntry::query()
                ->where('referenceable_id', $transaction->id)
                ->where('voucher_number', 'not like', '%__del_%')
                ->count(),
        );
    }

    public function test_auto_post_off_skips_journal_entry(): void
    {
        [$user, , $account, $category] = $this->prepareTreasuryContext(
            CategoryType::Income,
            'sales',
            AutoPostMode::Off,
        );

        app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '80',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $this->assertSame(0, JournalEntry::query()->count());
    }

    /**
     * @return array{0: User, 1: Business, 2: Account, 3: Category}
     */
    protected function prepareTreasuryContext(
        CategoryType $categoryType,
        string $systemKey,
        AutoPostMode $mode,
    ): array {
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

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        app(BusinessLedgerSettingsService::class)->update($business, [
            'auto_post_mode' => $mode,
        ]);

        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'current_balance' => '10000',
        ]);
        $account->syncFloatingDetail();

        $glAccount = GlAccount::findBySystemKey($business, $systemKey);
        $this->assertNotNull($glAccount);

        $parent = Category::factory()->system()->create([
            'type' => $categoryType,
            'code' => $categoryType->value.'-parent-'.$systemKey,
            'slug' => $categoryType->value.'-parent-'.$systemKey,
            'name' => 'Parent',
        ]);

        $leaf = Category::factory()->system()->childOf($parent)->create([
            'code' => $categoryType->value.'-leaf-'.$systemKey,
            'slug' => $categoryType->value.'-leaf-'.$systemKey,
            'name' => 'Leaf',
            'gl_account_id' => $glAccount->id,
        ]);

        return [$user, $business, $account, $leaf];
    }
}
