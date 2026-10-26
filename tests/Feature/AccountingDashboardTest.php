<?php

namespace Tests\Feature;

use App\Enums\Accounting\InvoiceAging;
use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Catalog\Product;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_finalized_sales_and_keeps_currencies_apart(): void
    {
        [$user, $business, $irr, $usd] = $this->prepareBusiness();
        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $irr->id,
            'name' => 'IRR Cash Desk',
            'current_balance' => '5000',
        ]);
        Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $usd->id,
            'name' => 'USD Wallet',
            'current_balance' => '3',
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Dashboard Widget',
            'track_inventory' => true,
            'stock_quantity' => '20',
            'min_stock' => '0',
        ]);
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Dashboard Bolt',
            'track_inventory' => true,
            'stock_quantity' => '1',
            'min_stock' => '5',
        ]);

        $sale = Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'type' => InvoiceType::Sale,
            'invoice_number' => 'SL-DASH-IRR',
            'issue_date' => now()->toDateString(),
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'total_amount' => '1000000',
            'paid_amount' => '1000000',
            'payment_status' => InvoicePaymentStatus::Paid,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => '2',
            'total' => '1000000',
        ]);
        Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'type' => InvoiceType::Return,
            'invoice_number' => 'RT-DASH-IRR',
            'issue_date' => now()->toDateString(),
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'total_amount' => '200000',
            'paid_amount' => '200000',
            'payment_status' => InvoicePaymentStatus::Paid,
        ]);
        Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'type' => InvoiceType::Sale,
            'invoice_number' => 'SL-DASH-USD',
            'issue_date' => now()->toDateString(),
            'currency' => 'USD',
            'exchange_rate' => '50000',
            'total_amount' => '10',
            'paid_amount' => '10',
            'payment_status' => InvoicePaymentStatus::Paid,
        ]);
        Invoice::factory()->purchase()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'invoice_number' => 'PR-DASH-STOCK',
            'issue_date' => now()->toDateString(),
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'total_amount' => '5000000',
            'paid_amount' => '5000000',
            'payment_status' => InvoicePaymentStatus::Paid,
        ]);

        $rent = Category::factory()->create([
            'business_id' => $business->id,
            'name' => 'Dashboard Rent',
        ]);
        Transaction::factory()->expense()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'category_id' => $rent->id,
            'transaction_date' => now()->toDateString(),
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'amount' => '300000',
            'base_amount' => '300000',
        ]);
        Transaction::factory()->expense()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
            'currency' => 'USD',
            'exchange_rate' => '50000',
            'amount' => '2',
            'base_amount' => '100000',
        ]);

        Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'invoice_number' => 'SL-AGE-CURRENT',
            'issue_date' => now()->subYear()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'total_amount' => '1000',
            'paid_amount' => '0',
        ]);
        Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'invoice_number' => 'SL-AGE-30',
            'issue_date' => now()->subYear()->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'total_amount' => '4000',
            'paid_amount' => '0',
        ]);
        Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'invoice_number' => 'SL-AGE-90',
            'issue_date' => now()->subYear()->toDateString(),
            'due_date' => now()->subDays(90)->toDateString(),
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'total_amount' => '9000',
            'paid_amount' => '0',
        ]);
        Invoice::factory()->purchase()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'invoice_number' => 'PR-AGE-40',
            'issue_date' => now()->subYear()->toDateString(),
            'due_date' => now()->subDays(40)->toDateString(),
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'total_amount' => '7000',
            'paid_amount' => '0',
        ]);

        $fiscalYear = FiscalYear::factory()->create([
            'business_id' => $business->id,
            'is_closed' => false,
        ]);
        JournalEntry::factory()->create([
            'business_id' => $business->id,
            'fiscal_year_id' => $fiscalYear->id,
            'created_by' => $user->id,
            'status' => JournalEntryStatus::Draft,
        ]);

        Cache::flush();

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.dashboard.index')
            ->assertSee(__('general.dashboard_trends'))
            ->assertSeeInOrder([
                __('general.dashboard_trends'),
                __('general.dashboard_follow_up'),
            ])
            ->assertSee('1,300,000')
            ->assertSee('400,000')
            ->assertSee('900,000')
            ->assertSee('155,000')
            ->assertSee('14,000')
            ->assertSee('7,000')
            ->assertSee('4,000')
            ->assertSee('9,000')
            ->assertSee('Dashboard Rent')
            ->assertSee('Dashboard Widget')
            ->assertSee('Dashboard Bolt')
            ->assertSee('SL-AGE-30')
            ->assertSee('SL-AGE-90')
            ->assertDontSee('SL-AGE-CURRENT')
            ->assertSee(__('general.dashboard_draft_count', ['count' => 1]))
            ->assertSee(__('general.dashboard_moodian_unsent'))
            ->set('currency', 'USD')
            ->assertSee('10.00')
            ->assertSee('2.00')
            ->assertSee('8.00')
            ->assertSee('3.00')
            ->assertSee('USD Wallet')
            ->assertDontSee('1,300,000')
            ->assertDontSee('IRR Cash Desk')
            ->assertDontSee('SL-AGE-90');
    }

    public function test_accountant_sees_follow_up_before_charts(): void
    {
        [$owner, $business] = $this->prepareBusiness();
        $accountant = User::factory()->create(['current_business_id' => $business->id]);
        BusinessUser::factory()->accountant()->create([
            'business_id' => $business->id,
            'user_id' => $accountant->id,
        ]);

        Cache::flush();

        Livewire::actingAs($accountant)
            ->test('pages::panel.accounting.dashboard.index')
            ->assertSeeInOrder([
                __('general.dashboard_follow_up'),
                __('general.dashboard_trends'),
            ]);

        $this->assertNotSame($owner->id, $accountant->id);
    }

    public function test_dashboard_hides_widgets_without_permission(): void
    {
        [, $business] = $this->prepareBusiness();
        $viewer = User::factory()->create(['current_business_id' => $business->id]);
        BusinessUser::factory()
            ->withPermissions([BusinessPermission::InvoiceView->value], BusinessRole::Viewer)
            ->create([
                'business_id' => $business->id,
                'user_id' => $viewer->id,
            ]);

        Cache::flush();

        Livewire::actingAs($viewer)
            ->test('pages::panel.accounting.dashboard.index')
            ->assertSee(__('general.dashboard_total_revenue'))
            ->assertSee(__('general.dashboard_overdue_invoices'))
            ->assertDontSee(__('general.dashboard_cash_balance'))
            ->assertDontSee(__('general.dashboard_bank_balances'))
            ->assertDontSee(__('general.dashboard_expense_breakdown'))
            ->assertDontSee(__('general.dashboard_cash_flow'))
            ->assertDontSee(__('general.create_journal_entry'));
    }

    public function test_invoice_aging_filter_limits_the_list(): void
    {
        [$user, $business] = $this->prepareBusiness();

        Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'invoice_number' => 'SL-FILTER-OLD',
            'due_date' => now()->subDays(90)->toDateString(),
            'total_amount' => '9000',
            'paid_amount' => '0',
        ]);
        Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'invoice_number' => 'SL-FILTER-NEW',
            'due_date' => now()->addDay()->toDateString(),
            'total_amount' => '1000',
            'paid_amount' => '0',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.invoice.index')
            ->set('aging', InvoiceAging::Over60->value)
            ->assertSee('SL-FILTER-OLD')
            ->assertDontSee('SL-FILTER-NEW');
    }

    public function test_transaction_category_filter_limits_the_list(): void
    {
        [$user, $business] = $this->prepareBusiness();
        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $business->baseBusinessCurrency()?->currency_id,
        ]);
        $rent = Category::factory()->create([
            'business_id' => $business->id,
            'name' => 'Filter Rent',
        ]);
        $ads = Category::factory()->create([
            'business_id' => $business->id,
            'name' => 'Filter Ads',
        ]);

        Transaction::factory()->expense()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'category_id' => $rent->id,
            'amount' => '111111',
            'base_amount' => '111111',
        ]);
        Transaction::factory()->expense()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'category_id' => $ads->id,
            'amount' => '222222',
            'base_amount' => '222222',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.transaction.index')
            ->set('categoryFilter', (string) $rent->id)
            ->assertSee('111,111')
            ->assertDontSee('222,222');
    }

    /**
     * @return array{0: User, 1: Business, 2?: Currency, 3?: Currency}
     */
    protected function prepareBusiness(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $irr = Currency::factory()->create([
            'code' => 'IRR',
            'name' => 'Rial',
            'decimal_places' => 0,
            'is_system' => true,
            'business_id' => null,
        ]);
        $usd = Currency::factory()->create([
            'code' => 'USD',
            'name' => 'Dollar',
            'decimal_places' => 2,
            'is_system' => true,
            'business_id' => null,
        ]);
        $business->activateCurrency($irr, '1', true);
        $business->activateCurrency($usd, '50000', false);

        return [$user, $business->fresh(), $irr, $usd];
    }
}
