<?php

namespace Tests\Feature;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Actions\Business\UpdateBusinessLedgerSettingsAction;
use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\AutoPostQueueStatus;
use App\Enums\Accounting\DetailType;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\TransactionType;
use App\Enums\Business\BusinessCategory;
use App\Enums\CategoryType;
use App\Jobs\Accounting\PostDailyGlBatchesJob;
use App\Models\Accounting\Account;
use App\Models\Accounting\AutoPostQueue;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use App\Services\Accounting\BusinessLedgerSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class GlCoreCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_batch_queues_a_document_and_the_nightly_job_posts_it(): void
    {
        [$user, $business, $account] = $this->prepareLedger(BusinessCategory::Grocery, AutoPostMode::DailyBatch);
        $sales = GlAccount::findBySystemKey($business, 'sales');
        $category = Category::factory()->create([
            'business_id' => $business->id,
            'type' => CategoryType::Income,
            'gl_account_id' => $sales?->id,
        ]);

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '80',
            'currency' => 'IRT',
            'exchange_rate' => '1',
        ]);

        $this->assertSame(0, JournalEntry::query()->count());
        $this->assertDatabaseHas('auto_post_queue', [
            'business_id' => $business->id,
            'source_id' => $transaction->id,
            'status' => AutoPostQueueStatus::Pending->value,
        ]);

        app(PostDailyGlBatchesJob::class)->handle(app(AutoPostingEngine::class));

        $entry = JournalEntry::query()->where('referenceable_id', $transaction->id)->first();

        $this->assertNotNull($entry);
        $this->assertSame(JournalEntryStatus::Posted, $entry->status);
        $this->assertSame(
            AutoPostQueueStatus::Posted,
            AutoPostQueue::query()->where('source_id', $transaction->id)->first()?->status,
        );
    }

    public function test_foreign_invoice_settlement_posts_exchange_gain(): void
    {
        [$user, $business, $account] = $this->prepareLedger(BusinessCategory::Grocery, AutoPostMode::Realtime);

        $usd = Currency::factory()->create([
            'code' => 'USD',
            'decimal_places' => 2,
            'is_system' => true,
            'business_id' => null,
        ]);
        $business->activateCurrency($usd, '50000');

        $party = Party::factory()->create(['business_id' => $business->id]);
        $invoice = Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'party_id' => $party->id,
            'currency' => 'USD',
            'exchange_rate' => '50000',
            'total_amount' => '100',
        ]);

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'invoice_id' => $invoice->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '100',
            'currency' => 'USD',
            'exchange_rate' => '52000',
        ]);

        $entry = JournalEntry::query()->where('referenceable_id', $transaction->id)->first();
        $gain = GlAccount::findBySystemKey($business, 'fx_gain');

        $this->assertNotNull($entry);
        $this->assertNotNull($gain);

        $line = JournalLine::query()
            ->where('journal_entry_id', $entry->id)
            ->where('gl_account_id', $gain->id)
            ->first();

        $this->assertNotNull($line);
        $this->assertSame(0, bccomp((string) $line->credit, '200000', 4));
        $this->assertSame(0, bccomp((string) $line->debit, '0', 4));
    }

    public function test_code_length_is_enforced_and_locked_after_accounts_exist(): void
    {
        [$user, $business] = $this->prepareLedger(BusinessCategory::Grocery, AutoPostMode::Off);

        Livewire::actingAs($user)
            ->test('accounting.gl-account.create')
            ->call('assignData', null)
            ->set('form.code', '99')
            ->set('form.name', 'Too long')
            ->call('save')
            ->assertHasErrors(['code']);

        $this->expectException(ValidationException::class);

        app(UpdateBusinessLedgerSettingsAction::class)->handle($business, [
            'voucher_numbering_mode' => 'yearly',
            'voucher_prefix' => null,
            'voucher_number_pad' => 4,
            'default_voucher_status' => 'draft',
            'auto_post_mode' => 'off',
            'group_code_length' => 3,
            'control_code_length' => 1,
            'subsidiary_code_length' => 2,
        ]);
    }

    public function test_service_template_omits_inventory_and_maps_services_as_sales(): void
    {
        [, $business] = $this->prepareBusiness(BusinessCategory::Education);

        app(SeedBusinessChartOfAccountsAction::class)->handle(
            $business,
            template: $business->category?->chartTemplate(),
        );
        $settings = app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        $this->assertNull(GlAccount::findBySystemKey($business, 'inventory'));
        $this->assertNull(GlAccount::findBySystemKey($business, 'cogs'));
        $this->assertNull(GlAccount::findBySystemKey($business, 'sales'));

        $services = GlAccount::findBySystemKey($business, 'services');
        $this->assertNotNull($services);
        $this->assertSame($services->id, $settings->default_sales_id);
    }

    public function test_contracting_template_requires_project_detail_on_income(): void
    {
        [, $business] = $this->prepareBusiness(BusinessCategory::Construction);

        app(SeedBusinessChartOfAccountsAction::class)->handle(
            $business,
            template: $business->category?->chartTemplate(),
        );

        $sales = GlAccount::findBySystemKey($business, 'sales');

        $this->assertNotNull($sales);
        $this->assertSame(DetailType::Project, $sales->requires_detail_level_5_type);
    }

    /**
     * @return array{0: User, 1: Business, 2: Account}
     */
    protected function prepareLedger(BusinessCategory $category, AutoPostMode $mode): array
    {
        [$user, $business, $currency] = $this->prepareBusiness($category);

        FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);
        BusinessLedgerSetting::forBusiness($business);
        app(BusinessLedgerSettingsService::class)->update($business, [
            'auto_post_mode' => $mode,
        ]);

        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'current_balance' => '0',
        ]);
        $account->syncFloatingDetail();

        return [$user, $business, $account];
    }

    /**
     * @return array{0: User, 1: Business, 2: Currency}
     */
    protected function prepareBusiness(BusinessCategory $category): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'category' => $category,
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();

        $currency = Currency::factory()->create([
            'code' => 'IRT',
            'decimal_places' => 0,
            'is_system' => true,
            'business_id' => null,
        ]);
        $business->activateCurrency($currency, '1', true);

        return [$user, $business, $currency];
    }
}
