<?php

namespace Tests\Feature;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Actions\Invoices\FinalizeInvoiceAction;
use App\Actions\Invoices\SaveInvoiceAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Models\Catalog\Product;
use App\Models\User;
use App\Services\Accounting\BusinessLedgerSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LedgerDefaultAccountsAndAutoPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_maps_default_accounts_from_system_keys(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        $settings = app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        $this->assertNotNull($settings->default_accounts_receivable_id);
        $this->assertNotNull($settings->default_sales_id);
        $this->assertNotNull($settings->default_inventory_id);

        $this->assertSame(
            GlAccount::findBySystemKey($business, 'accounts_receivable')?->id,
            $settings->default_accounts_receivable_id,
        );
        $this->assertSame(
            GlAccount::findBySystemKey($business, 'sales')?->id,
            $settings->default_sales_id,
        );
        $this->assertSame($user->id, $business->owner_id);
    }

    public function test_ledger_settings_page_can_sync_and_save_defaults(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        app(SeedBusinessChartOfAccountsAction::class)->handle($business);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.ledger-setting.index')
            ->call('syncDefaults')
            ->assertHasNoErrors();

        $settings = BusinessLedgerSetting::query()->where('business_id', $business->id)->first();
        $this->assertNotNull($settings?->default_sales_id);

        $sales = GlAccount::findBySystemKey($business, 'sales');
        $receivable = GlAccount::findBySystemKey($business, 'accounts_receivable');

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.ledger-setting.index')
            ->set('tab', 'defaults')
            ->set('form.default_sales_id', $sales?->id)
            ->set('form.default_accounts_receivable_id', $receivable?->id)
            ->call('save')
            ->assertHasNoErrors();

        $fresh = app(BusinessLedgerSettingsService::class)->get($business);
        $this->assertSame($sales?->id, $fresh->default_sales_id);
        $this->assertSame($receivable?->id, $fresh->default_accounts_receivable_id);
    }

    public function test_finalize_sale_with_realtime_auto_post_creates_posted_journal_entry(): void
    {
        [$user, $business, $party, $product] = $this->seedSaleContext();

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        app(BusinessLedgerSettingsService::class)->update($business, [
            'auto_post_mode' => AutoPostMode::Realtime,
        ]);

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'global_discount' => '1000',
            'global_tax' => '500',
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '2',
                    'unit_price' => '10000',
                    'discount_amount' => '0',
                    'tax_amount' => '900',
                    'sort_order' => 0,
                ],
            ],
        ]);

        $finalized = app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $entry = JournalEntry::query()
            ->where('business_id', $business->id)
            ->where('referenceable_type', $finalized->getMorphClass())
            ->where('referenceable_id', $finalized->id)
            ->with('lines')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(JournalEntryStatus::Posted, $entry->status);
        $this->assertGreaterThanOrEqual(2, $entry->lines->count());

        $debit = $entry->lines->reduce(fn (string $sum, $line) => bcadd($sum, (string) $line->debit, 4), '0');
        $credit = $entry->lines->reduce(fn (string $sum, $line) => bcadd($sum, (string) $line->credit, 4), '0');
        $this->assertSame(0, bccomp($debit, $credit, 4));

        $settings = app(BusinessLedgerSettingsService::class)->get($business);
        $arLine = $entry->lines->firstWhere('gl_account_id', $settings->default_accounts_receivable_id);
        $this->assertNotNull($arLine);
        $this->assertSame(0, bccomp((string) $arLine->debit, (string) $finalized->total_amount, 4));
        $this->assertSame($party->id, $arLine->party_id);
    }

    public function test_finalize_sale_with_manual_review_creates_draft_journal_entry(): void
    {
        [$user, $business, $party, $product] = $this->seedSaleContext();

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        app(BusinessLedgerSettingsService::class)->update($business, [
            'auto_post_mode' => AutoPostMode::ManualReview,
        ]);

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '1',
                    'unit_price' => '25000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ]);

        $finalized = app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $entry = JournalEntry::query()
            ->where('referenceable_id', $finalized->id)
            ->where('referenceable_type', $finalized->getMorphClass())
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(JournalEntryStatus::PendingReview, $entry->status);
    }

    public function test_finalize_with_auto_post_off_creates_no_journal_entry(): void
    {
        [$user, $business, $party, $product] = $this->seedSaleContext();

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '1',
                    'unit_price' => '1000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ]);

        app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->assertSame(0, JournalEntry::query()->where('business_id', $business->id)->count());
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

    /**
     * @return array{0: User, 1: Business, 2: Party, 3: Product}
     */
    protected function seedSaleContext(string $stock = '10'): array
    {
        [$user, $business] = $this->actingBusinessUser();

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_customer' => true,
            'balance' => '0',
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => $stock,
            'sale_price' => '10000',
        ]);

        return [$user, $business, $party, $product];
    }
}
