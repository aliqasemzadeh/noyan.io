<?php

namespace Tests\Feature;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Actions\Ledger\ConvertManualSettlementJournalsAction;
use App\Actions\Ledger\DeleteJournalEntryAction;
use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Accounting\TransactionType;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Models\BusinessUser;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use App\Services\Accounting\BusinessLedgerSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class LedgerGlSyncButtonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_settings_page_exposes_gl_maintenance_actions(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        BusinessLedgerSetting::forBusiness($business);
        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.ledger-setting.index')
            ->set('tab', 'auto_post')
            ->call('backfillMissingJournals')
            ->assertHasNoErrors()
            ->call('convertManualJournals')
            ->assertHasNoErrors()
            ->call('syncGlDocuments')
            ->assertHasNoErrors();
    }

    public function test_convert_manual_settlement_journal_creates_income_and_clears_party_balance(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        $settings = app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        app(BusinessLedgerSettingsService::class)->update($business, [
            'auto_post_mode' => AutoPostMode::Realtime,
        ]);

        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
            'is_system' => true,
            'business_id' => null,
        ]);
        $business->activateCurrency($currency, '1', true);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'balance' => '74800000',
        ]);

        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'current_balance' => '0',
        ]);
        $account->syncFloatingDetail();

        $invoice = Invoice::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'party_id' => $party->id,
            'type' => InvoiceType::Sale,
            'total_amount' => '74800000',
            'paid_amount' => '0',
            'payment_status' => InvoicePaymentStatus::Unpaid,
            'finalized_at' => now(),
            'issue_date' => now()->toDateString(),
        ]);

        $cashGl = GlAccount::query()->findOrFail($settings->default_cash_and_bank_id);
        $arGl = GlAccount::query()->findOrFail($settings->default_accounts_receivable_id);

        $entry = JournalEntry::factory()->posted()->create([
            'business_id' => $business->id,
            'fiscal_year_id' => FiscalYear::query()->where('business_id', $business->id)->value('id'),
            'created_by' => $user->id,
            'description' => 'Manual remittance',
            'referenceable_type' => null,
            'referenceable_id' => null,
        ]);

        JournalLine::factory()->debit('74800000')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $cashGl->id,
            'account_id' => $account->id,
            'party_id' => null,
            'category_id' => null,
        ]);

        JournalLine::factory()->credit('74800000')->create([
            'journal_entry_id' => $entry->id,
            'gl_account_id' => $arGl->id,
            'account_id' => null,
            'party_id' => $party->id,
            'category_id' => null,
        ]);

        $stats = app(ConvertManualSettlementJournalsAction::class)->handle($business, $user);

        $this->assertSame(1, $stats['converted']);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'deleted_at' => null,
        ]);
        $this->assertNotNull($entry->fresh()->referenceable_id);

        $transaction = Transaction::query()
            ->where('business_id', $business->id)
            ->where('party_id', $party->id)
            ->where('type', TransactionType::Income)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame($invoice->id, $transaction->invoice_id);
        $this->assertSame($transaction->id, (int) $entry->fresh()->referenceable_id);
        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '0', 18));
        $this->assertSame(0, bccomp((string) $account->fresh()->current_balance, '74800000', 18));
        $this->assertSame(InvoicePaymentStatus::Paid, $invoice->fresh()->payment_status);
    }

    public function test_deleting_transaction_linked_journal_deletes_the_transaction(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
            'is_system' => true,
            'business_id' => null,
        ]);
        $business->activateCurrency($currency, '1', true);

        FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        app(BusinessLedgerSettingsService::class)->update($business, [
            'auto_post_mode' => AutoPostMode::Realtime,
        ]);

        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'current_balance' => '10000',
        ]);
        $account->syncFloatingDetail();

        $salesGl = GlAccount::findBySystemKey($business, 'sales');
        $this->assertNotNull($salesGl);

        $parent = Category::factory()->system()->create([
            'type' => CategoryType::Income,
            'code' => 'income-parent-sales',
            'slug' => 'income-parent-sales',
            'name' => 'Parent',
        ]);
        $category = Category::factory()->system()->childOf($parent)->create([
            'code' => 'income-leaf-sales',
            'slug' => 'income-leaf-sales',
            'name' => 'Leaf',
            'gl_account_id' => $salesGl->id,
        ]);

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '1000',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $entry = JournalEntry::query()
            ->where('referenceable_type', $transaction->getMorphClass())
            ->where('referenceable_id', $transaction->id)
            ->first();

        $this->assertNotNull($entry);

        app(DeleteJournalEntryAction::class)->handle($user, $entry);

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
        $this->assertSoftDeleted('journal_entries', ['id' => $entry->id]);
    }

    public function test_owner_can_soft_delete_posted_manual_journal(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $entry = JournalEntry::factory()->posted()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'referenceable_type' => null,
            'referenceable_id' => null,
        ]);

        app(DeleteJournalEntryAction::class)->handle($user, $entry);

        $this->assertSoftDeleted('journal_entries', ['id' => $entry->id]);
    }

    public function test_non_owner_cannot_delete_posted_manual_journal(): void
    {
        [$owner, $business] = $this->actingBusinessUser();

        $member = User::factory()->create(['current_business_id' => $business->id]);
        BusinessUser::factory()->accountant()->create([
            'business_id' => $business->id,
            'user_id' => $member->id,
        ]);

        $entry = JournalEntry::factory()->posted()->create([
            'business_id' => $business->id,
            'created_by' => $owner->id,
            'referenceable_type' => null,
            'referenceable_id' => null,
        ]);

        $this->expectException(ValidationException::class);
        app(DeleteJournalEntryAction::class)->handle($member, $entry);
    }

    /**
     * @return array{0: User, 1: Business}
     */
    protected function actingBusinessUser(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        return [$user, $business];
    }
}
