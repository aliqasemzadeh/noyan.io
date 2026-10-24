<?php

namespace Tests\Feature;

use App\Ai\Agents\AccountingAssistant;
use App\Ai\Tools\CreateTransaction;
use App\Enums\Accounting\TransactionType;
use App\Enums\AccountSubType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use App\Support\LocaleDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

class AccountingAiTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_creates_expense_transaction_for_existing_account(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'type' => 'expense',
            'amount' => '1,500,000',
            'account_number' => '5199858647',
            'transaction_date' => now()->toDateString(),
            'note' => 'برداشت کارت',
        ]));

        $this->assertSame(__('general.ai_transaction_created', [
            'type' => TransactionType::Expense->label(),
            'amount' => '1500000',
            'account' => $account->name,
            'date' => LocaleDate::formatDate(now()->toDateString()),
        ]), $result);

        $this->assertDatabaseHas('transactions', [
            'business_id' => $business->id,
            'account_id' => $account->id,
            'type' => TransactionType::Expense->value,
            'amount' => '1500000',
            'note' => 'برداشت کارت',
        ]);

        $this->assertDatabaseCount('accounting_accounts', 1);
        $this->assertSame('1', (string) Account::query()->count());
    }

    public function test_tool_creates_income_transaction_with_persian_digits(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'type' => 'income',
            'amount' => '۲,۵۰۰,۰۰۰',
            'account_number' => '۵۱۹۹۸۵۸۶۴۷',
        ]));

        $this->assertDatabaseHas('transactions', [
            'business_id' => $business->id,
            'account_id' => $account->id,
            'type' => TransactionType::Income->value,
            'amount' => '2500000',
        ]);
    }

    public function test_tool_does_not_create_account_when_missing(): void
    {
        [$user, $business] = $this->actingBusinessOwnerWithBankAccount();

        $accountCountBefore = Account::query()->count();

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'type' => 'expense',
            'amount' => '1000',
            'account_number' => '9999999999',
        ]));

        $this->assertSame(__('general.ai_transaction_account_not_found'), $result);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame($accountCountBefore, Account::query()->count());
        $this->assertSame($business->id, $user->current_business_id);
    }

    public function test_tool_resolves_account_by_name(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'type' => 'expense',
            'amount' => '500',
            'account_name' => $account->name,
        ]));

        $this->assertStringContainsString($account->name, $result);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount' => '500',
        ]);
    }

    public function test_composer_dispatches_transaction_table_event(): void
    {
        AccountingAssistant::fake([__('general.ai_transaction_created', [
            'type' => TransactionType::Expense->label(),
            'amount' => '1500000',
            'account' => 'حساب جاری ملت',
            'date' => LocaleDate::formatDate(now()->toDateString()),
        ])]);

        [$user] = $this->actingBusinessOwnerWithBankAccount();

        $prompt = "این پیامک را به‌عنوان تراکنش ثبت کن:\nبرداشت از حساب5199858647\nمبلغ 1,500,000";

        Livewire::actingAs($user)
            ->test('ai.composer', ['context' => 'accounting'])
            ->set('prompt', $prompt)
            ->call('sendPrompt')
            ->assertHasNoErrors()
            ->assertDispatched('panels.accounting.account.index.table')
            ->assertDispatched('panels.accounting.transaction.index.table');

        AccountingAssistant::assertPrompted($prompt);
    }

    public function test_tool_creates_batch_of_transactions(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'transactions' => [
                [
                    'type' => 'expense',
                    'amount' => '1000',
                    'account_number' => '5199858647',
                    'note' => 'برداشت ۱',
                ],
                [
                    'type' => 'income',
                    'amount' => '2000',
                    'account_number' => '5199858647',
                    'note' => 'واریز ۱',
                ],
            ],
        ]));

        $this->assertStringContainsString('2', $result);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'type' => TransactionType::Expense->value,
            'amount' => '1000',
        ]);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'type' => TransactionType::Income->value,
            'amount' => '2000',
        ]);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_tool_batch_continues_when_one_item_fails(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'transactions' => [
                [
                    'type' => 'expense',
                    'amount' => '1000',
                    'account_number' => '9999999999',
                ],
                [
                    'type' => 'income',
                    'amount' => '2500',
                    'account_number' => '5199858647',
                ],
            ],
        ]));

        $this->assertStringContainsString(__('general.ai_transaction_account_not_found'), $result);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount' => '2500',
        ]);
    }

    public function test_tool_previews_without_creating_when_not_confirmed(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $expected = bcsub((string) $account->current_balance, '1500000', 0);

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => false,
            'type' => 'expense',
            'amount' => '1500000',
            'account_number' => '5199858647',
            'expected_balance' => $expected,
        ]));

        $this->assertStringContainsString(__('general.ai_transaction_preview_ask_confirm'), $result);
        $this->assertStringContainsString($account->name, $result);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(
            (string) $account->current_balance,
            (string) $account->fresh()->current_balance,
        );
    }

    public function test_tool_defaults_to_preview_when_confirmed_omitted(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $result = (string) (new CreateTransaction)->handle(new Request([
            'type' => 'expense',
            'amount' => '1500000',
            'account_number' => '5199858647',
        ]));

        $this->assertStringContainsString(__('general.ai_transaction_preview_ask_confirm'), $result);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tool_preview_rejects_mismatched_expected_balance_without_writing(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => false,
            'type' => 'expense',
            'amount' => '1500000',
            'account_number' => '5199858647',
            'expected_balance' => '1',
        ]));

        $this->assertStringContainsString($account->name, $result);
        $this->assertStringContainsString($business->name, $result);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tool_accepts_matching_expected_balance(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $expected = bcsub((string) $account->current_balance, '1500000', 0);

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'type' => 'expense',
            'amount' => '1500000',
            'account_number' => '5199858647',
            'expected_balance' => $expected,
        ]));

        $this->assertSame(__('general.ai_transaction_created', [
            'type' => TransactionType::Expense->label(),
            'amount' => '1500000',
            'account' => $account->name,
            'date' => LocaleDate::formatDate(now()->toDateString()),
        ]), $result);

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_tool_rejects_mismatched_expected_balance(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'type' => 'expense',
            'amount' => '1500000',
            'account_number' => '5199858647',
            'expected_balance' => '1',
        ]));

        $this->assertStringContainsString($account->name, $result);
        $this->assertStringContainsString($business->name, $result);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tool_creates_fee_transaction_when_balance_gap_matches_shetab_fee(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount(
            currencyCode: 'IRT',
            currentBalance: '5000000',
        );

        $amount = '1000000';
        $fee = '1100';
        $expected = bcsub(bcsub('5000000', $amount, 0), $fee, 0);

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'type' => 'expense',
            'amount' => $amount,
            'account_number' => '5199858647',
            'expected_balance' => $expected,
            'note' => 'کارت به کارت',
        ]));

        $this->assertSame(__('general.ai_transaction_created_with_fee', [
            'type' => TransactionType::Expense->label(),
            'amount' => $amount,
            'fee' => $fee,
            'account' => $account->name,
            'date' => LocaleDate::formatDate(now()->toDateString()),
        ]), $result);

        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount' => $amount,
            'note' => 'کارت به کارت',
        ]);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount' => $fee,
            'note' => __('general.ai_transaction_fee_note'),
        ]);
        $this->assertSame(0, bccomp($expected, (string) $account->fresh()->current_balance, 18));
    }

    public function test_tool_batch_preview_uses_running_balance_for_expected_checks(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount(
            currentBalance: '10000000',
        );

        $firstAmount = '1500000';
        $secondAmount = '2000000';
        $afterFirst = bcsub('10000000', $firstAmount, 0);
        $afterSecond = bcsub($afterFirst, $secondAmount, 0);

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => false,
            'transactions' => [
                [
                    'type' => 'expense',
                    'amount' => $firstAmount,
                    'account_number' => '5199858647',
                    'expected_balance' => $afterFirst,
                ],
                [
                    'type' => 'expense',
                    'amount' => $secondAmount,
                    'account_number' => '5199858647',
                    'expected_balance' => $afterSecond,
                ],
            ],
        ]));

        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString(__('general.ai_transaction_preview_ask_confirm'), $result);
        $this->assertStringContainsString('قابل ثبت', $result);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tool_batch_creates_with_sequential_expected_balances(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount(
            currentBalance: '10000000',
        );

        $firstAmount = '1500000';
        $secondAmount = '2000000';
        $afterFirst = bcsub('10000000', $firstAmount, 0);
        $afterSecond = bcsub($afterFirst, $secondAmount, 0);

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'transactions' => [
                [
                    'type' => 'expense',
                    'amount' => $firstAmount,
                    'account_number' => '5199858647',
                    'expected_balance' => $afterFirst,
                ],
                [
                    'type' => 'expense',
                    'amount' => $secondAmount,
                    'account_number' => '5199858647',
                    'expected_balance' => $afterSecond,
                ],
            ],
        ]));

        $this->assertStringContainsString('2', $result);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertSame(0, bccomp($afterSecond, (string) $account->fresh()->current_balance, 18));
    }

    public function test_instructions_include_active_business_and_batch_guidance(): void
    {
        [$user, $business] = $this->actingBusinessOwnerWithBankAccount();

        $instructions = (string) (new AccountingAssistant)->instructions();

        $this->assertStringContainsString($business->name, $instructions);
        $this->assertStringContainsString('transactions', $instructions);
        $this->assertStringContainsString('expected_balance', $instructions);
        $this->assertStringContainsString('confirmed=false', $instructions);
        $this->assertStringContainsString('confirmed=true', $instructions);
        $this->assertStringContainsString('02/08', $instructions);
    }

    public function test_tool_preview_accepts_short_jalali_sms_date_near_today(): void
    {
        $this->travelTo(Jalalian::fromFormat('Y/m/d', '1405/08/02')->toCarbon()->setTime(12, 0));

        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => false,
            'type' => 'expense',
            'amount' => '1500000',
            'account_number' => '5199858647',
            'transaction_date' => '02/08',
            'expected_balance' => bcsub((string) $account->current_balance, '1500000', 0),
        ]));

        $this->assertStringContainsString(LocaleDate::formatDate(now()->toDateString()), $result);
        $this->assertStringContainsString(__('general.ai_transaction_preview_ask_confirm'), $result);
        $this->assertStringNotContainsString('هشدار:', $result);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tool_preview_warns_when_date_is_far_from_today(): void
    {
        $this->travelTo(Jalalian::fromFormat('Y/m/d', '1405/08/02')->toCarbon()->setTime(12, 0));

        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $farDate = LocaleDate::toStorageDate('1405/01/01');

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => false,
            'type' => 'expense',
            'amount' => '1500000',
            'account_number' => '5199858647',
            'transaction_date' => '1405/01/01',
            'expected_balance' => bcsub((string) $account->current_balance, '1500000', 0),
        ]));

        $this->assertStringContainsString(__('general.ai_transaction_date_far', [
            'date' => LocaleDate::formatDate($farDate),
            'days' => (int) abs(now()->startOfDay()->diffInDays($farDate)),
        ]), $result);
        $this->assertStringContainsString(__('general.ai_transaction_preview_ask_confirm'), $result);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tool_preview_warns_when_date_is_before_latest_account_transaction(): void
    {
        $this->travelTo(Jalalian::fromFormat('Y/m/d', '1405/08/02')->toCarbon()->setTime(12, 0));

        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        Transaction::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'type' => TransactionType::Income,
            'transaction_date' => now()->toDateString(),
            'currency' => $currency->code,
            'amount' => '1000',
            'base_amount' => '1000',
        ]);

        $older = LocaleDate::toStorageDate('1405/08/01');

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => false,
            'type' => 'expense',
            'amount' => '1500000',
            'account_number' => '5199858647',
            'transaction_date' => '01/08',
            'expected_balance' => bcsub((string) $account->fresh()->current_balance, '1500000', 0),
        ]));

        $this->assertStringContainsString(__('general.ai_transaction_date_out_of_order', [
            'date' => LocaleDate::formatDate($older),
            'last' => LocaleDate::formatDate(now()->toDateString()),
        ]), $result);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_tool_batch_reorders_by_date_for_expected_balance_checks(): void
    {
        $this->travelTo(Jalalian::fromFormat('Y/m/d', '1405/08/02')->toCarbon()->setTime(12, 0));

        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount(
            currentBalance: '10000000',
        );

        $firstAmount = '1500000';
        $secondAmount = '2000000';
        $afterFirst = bcsub('10000000', $firstAmount, 0);
        $afterSecond = bcsub($afterFirst, $secondAmount, 0);

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => false,
            'transactions' => [
                [
                    'type' => 'expense',
                    'amount' => $secondAmount,
                    'account_number' => '5199858647',
                    'transaction_date' => '02/08',
                    'expected_balance' => $afterSecond,
                ],
                [
                    'type' => 'expense',
                    'amount' => $firstAmount,
                    'account_number' => '5199858647',
                    'transaction_date' => '01/08',
                    'expected_balance' => $afterFirst,
                ],
            ],
        ]));

        $this->assertStringContainsString(__('general.ai_transaction_batch_reordered_by_date'), $result);
        $this->assertStringContainsString(__('general.ai_transaction_preview_ask_confirm'), $result);
        $this->assertStringContainsString('قابل ثبت', $result);
        $this->assertDatabaseCount('transactions', 0);
    }

    /**
     * @return array{0: User, 1: Business, 2: Currency, 3: Account}
     */
    protected function actingBusinessOwnerWithBankAccount(
        string $currencyCode = 'IRR',
        string $currentBalance = '644031211',
    ): array {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'name' => 'کسب‌وکار تست',
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create([
            'code' => $currencyCode,
            'decimal_places' => 0,
        ]);
        $business->activateCurrency($currency, '1', true);

        $account = Account::factory()->bank()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'حساب جاری ملت',
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'sub_type' => AccountSubType::Bank,
            'opening_balance' => $currentBalance,
            'current_balance' => $currentBalance,
        ]);

        $this->actingAs($user);

        return [$user, $business, $currency, $account];
    }
}
