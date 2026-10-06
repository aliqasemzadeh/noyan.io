<?php

namespace Tests\Feature;

use App\Ai\Tools\GetDailyExpenses;
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
use Tests\TestCase;

class AccountingAiDailyExpensesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_reports_todays_expenses_with_total(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        Transaction::factory()->expense()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
            'currency' => $currency->code,
            'amount' => '1500000',
            'base_amount' => '1500000',
            'note' => 'برداشت کارت',
        ]);

        Transaction::factory()->expense()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
            'currency' => $currency->code,
            'amount' => '500000',
            'base_amount' => '500000',
            'note' => 'خرید',
        ]);

        Transaction::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'type' => TransactionType::Income,
            'transaction_date' => now()->toDateString(),
            'currency' => $currency->code,
            'amount' => '9000000',
            'base_amount' => '9000000',
        ]);

        Transaction::factory()->expense()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'transaction_date' => now()->subDay()->toDateString(),
            'currency' => $currency->code,
            'amount' => '100000',
            'base_amount' => '100000',
        ]);

        $result = (string) (new GetDailyExpenses)->handle(new Request([]));

        $this->assertSame(__('general.ai_daily_expenses_summary', [
            'date' => LocaleDate::formatDate(now()->toDateString()),
            'total' => number_format(2000000),
            'count' => 2,
            'items' => implode("\n", [
                __('general.ai_daily_expenses_item', [
                    'n' => 1,
                    'amount' => number_format(500000).' '.$currency->code,
                    'account' => $account->name,
                    'detail' => ' — خرید',
                ]),
                __('general.ai_daily_expenses_item', [
                    'n' => 2,
                    'amount' => number_format(1500000).' '.$currency->code,
                    'account' => $account->name,
                    'detail' => ' — برداشت کارت',
                ]),
            ]),
        ]), $result);
    }

    public function test_tool_returns_empty_message_when_no_expenses(): void
    {
        $this->actingBusinessOwnerWithBankAccount();

        $result = (string) (new GetDailyExpenses)->handle(new Request([]));

        $this->assertSame(__('general.ai_daily_expenses_empty', [
            'date' => LocaleDate::formatDate(now()->toDateString()),
        ]), $result);
    }

    public function test_tool_accepts_explicit_date(): void
    {
        [$user, $business, $currency, $account] = $this->actingBusinessOwnerWithBankAccount();

        $date = now()->subDays(3)->toDateString();

        Transaction::factory()->expense()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'transaction_date' => $date,
            'currency' => $currency->code,
            'amount' => '250000',
            'base_amount' => '250000',
            'note' => 'دیروز',
        ]);

        $result = (string) (new GetDailyExpenses)->handle(new Request([
            'date' => LocaleDate::formatDate($date),
        ]));

        $this->assertStringContainsString(number_format(250000), $result);
        $this->assertStringContainsString(LocaleDate::formatDate($date), $result);
        $this->assertStringContainsString($account->name, $result);
    }

    public function test_tool_requires_business(): void
    {
        $user = User::factory()->create(['current_business_id' => null]);
        $this->actingAs($user);

        $result = (string) (new GetDailyExpenses)->handle(new Request([]));

        $this->assertSame(__('general.business_required'), $result);
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
