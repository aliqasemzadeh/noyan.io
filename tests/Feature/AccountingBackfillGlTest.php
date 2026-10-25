<?php

namespace Tests\Feature;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\TransactionType;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Business;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use App\Services\Accounting\BusinessLedgerSettingsService;
use App\Support\Accounting\CategoryGlAccountMap;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingBackfillGlTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_category_resolves_gl_account_by_code_map(): void
    {
        [$user, $business] = $this->seedBusinessWithCoa();
        $this->seed(CategorySeeder::class);

        $bankFee = Category::query()->where('code', 'bank_fee')->whereNull('business_id')->first();
        $this->assertNotNull($bankFee);
        $this->assertNull($bankFee->gl_account_id);

        $resolved = CategoryGlAccountMap::resolve($business, $bankFee);
        $this->assertNotNull($resolved);
        $this->assertSame(
            GlAccount::findBySystemKey($business, 'bank_fees')?->id,
            $resolved->id,
        );
        $this->assertSame($user->id, $business->owner_id);
    }

    public function test_backfill_creates_draft_vouchers_for_old_transactions_even_when_auto_post_off(): void
    {
        [$user, $business, $account] = $this->seedBusinessWithCoa();
        $this->seed(CategorySeeder::class);

        FiscalYear::factory()->create([
            'business_id' => $business->id,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        app(BusinessLedgerSettingsService::class)->update($business, [
            'auto_post_mode' => AutoPostMode::Off,
        ]);

        $leaf = Category::query()->where('code', 'bank_fee')->whereNull('business_id')->first();
        $this->assertNotNull($leaf);

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'category_id' => $leaf->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '120',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $this->assertSame(0, JournalEntry::query()->count());

        $this->artisan('accounting:backfill-gl', [
            'business' => $business->id,
            '--skip-seed' => true,
        ])->assertSuccessful();

        $entry = JournalEntry::query()
            ->where('referenceable_type', $transaction->getMorphClass())
            ->where('referenceable_id', $transaction->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(JournalEntryStatus::Draft, $entry->status);

        $this->artisan('accounting:backfill-gl', [
            'business' => $business->id,
            '--skip-seed' => true,
        ])->assertSuccessful();

        $this->assertSame(1, JournalEntry::query()->where('referenceable_id', $transaction->id)->count());
    }

    public function test_migrate_categories_links_business_owned_rows(): void
    {
        [, $business] = $this->seedBusinessWithCoa();

        $sales = GlAccount::findBySystemKey($business, 'sales');
        $this->assertNotNull($sales);

        $category = Category::factory()->create([
            'business_id' => $business->id,
            'type' => CategoryType::Income,
            'code' => 'sales',
            'slug' => 'biz-sales-'.$business->id,
            'gl_account_id' => null,
            'is_system' => false,
        ]);

        $this->artisan('accounting:migrate-categories-to-gl', [
            'business' => $business->id,
        ])->assertSuccessful();

        $this->assertSame($sales->id, $category->fresh()->gl_account_id);
    }

    /**
     * @return array{0: User, 1: Business, 2?: Account}
     */
    protected function seedBusinessWithCoa(bool $withAccount = true): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);
        app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

        if (! $withAccount) {
            return [$user, $business];
        }

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
        ]);
        $account->syncFloatingDetail();

        return [$user, $business, $account];
    }
}
