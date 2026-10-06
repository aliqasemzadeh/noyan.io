<?php

namespace Tests\Feature;

use App\Ai\Agents\AccountingAssistant;
use App\Ai\Tools\CreateTransaction;
use App\Enums\Accounting\TransactionType;
use App\Enums\AccountSubType;
use App\Models\Accounting\Account;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use App\Support\LocaleDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingAiTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_creates_expense_transaction_for_existing_account(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $result = (string) (new CreateTransaction)->handle(new Request([
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

    /**
     * @return array{0: User, 1: Business, 2: Currency, 3: Account}
     */
    protected function actingBusinessOwnerWithBankAccount(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create([
            'code' => 'IRR',
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
            'opening_balance' => '644031211',
            'current_balance' => '644031211',
        ]);

        $this->actingAs($user);

        return [$user, $business, $currency, $account];
    }
}
