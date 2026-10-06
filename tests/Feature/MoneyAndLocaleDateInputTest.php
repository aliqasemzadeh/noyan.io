<?php

namespace Tests\Feature;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use App\Support\LocaleDate;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

class MoneyAndLocaleDateInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_normalize_strips_commas_and_persian_digits(): void
    {
        $this->assertSame('1234567', Money::normalize('1,234,567'));
        $this->assertSame('1234.50', Money::normalize('1,234.50'));
        $this->assertSame('1234', Money::normalize('۱,۲۳۴'));
        $this->assertSame('', Money::normalize('   '));
    }

    public function test_money_format_adds_thousand_separators_and_decimals(): void
    {
        $this->assertSame('1,234,567', Money::format('1234567'));
        $this->assertSame('1,234,567.50', Money::format('1234567.5', 2));
        $this->assertSame('1,234', Money::format('۱,۲۳۴'));
        $this->assertSame('-2,500.00', Money::format('-2500', 2));
        $this->assertSame('0', Money::format(null));
        $this->assertSame('0.00', Money::format('', 2));
    }

    public function test_locale_date_format_input_and_storage_roundtrip(): void
    {
        App::setLocale('fa');

        $carbon = Jalalian::fromFormat('Y/m/d', '1403/01/15')->toCarbon()->startOfDay();
        $this->assertSame('1403/01/15', LocaleDate::formatInput($carbon));
        $this->assertSame('2024-04-03', LocaleDate::toStorageDate('1403/01/15'));

        App::setLocale('en');
        $this->assertSame('2024-04-03', LocaleDate::formatInput($carbon));
        $this->assertSame('2024-04-03', LocaleDate::toStorageDate('2024-04-03'));
    }

    public function test_transaction_create_accepts_masked_amount_and_jalali_date(): void
    {
        [$user, $account, $leaf] = $this->prepareContext();

        App::setLocale('fa');
        $jalaliToday = LocaleDate::formatInput(now());

        Livewire::actingAs($user)
            ->test('accounting.transaction.create')
            ->assertSee('mask:dynamic', false)
            ->set('form.type', TransactionType::Income->value)
            ->set('form.account_id', $account->id)
            ->set('form.category_id', $leaf->id)
            ->set('form.amount', '1,500')
            ->set('form.transaction_date', $jalaliToday)
            ->call('save')
            ->assertHasNoErrors();

        $transaction = Transaction::query()->first();

        $this->assertNotNull($transaction);
        $this->assertSame(0, bccomp((string) $transaction->amount, '1500', 18));
        $this->assertSame(now()->toDateString(), $transaction->transaction_date->toDateString());
    }

    public function test_transaction_create_renders_money_mask_attribute(): void
    {
        [$user] = $this->prepareContext();

        Livewire::actingAs($user)
            ->test('accounting.transaction.create')
            ->assertSeeHtml('x-mask:dynamic="$money($input)"');
    }

    public function test_party_view_renders_jalali_date_range_when_locale_is_fa(): void
    {
        [$user, $account] = $this->prepareContext();
        $party = Party::factory()->create([
            'business_id' => $account->business_id,
            'is_active' => true,
        ]);

        App::setLocale('fa');

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.party.view', ['party' => $party])
            ->assertSee(__('general.date_range_placeholder'));
    }

    /**
     * @return array{0: User, 1: Account, 2: Category}
     */
    protected function prepareContext(): array
    {
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

        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'current_balance' => '10000',
            'is_active' => true,
        ]);

        $parent = Category::factory()->system()->income()->create([
            'code' => 'sales-parent-money',
            'slug' => 'sales-parent-money',
            'name' => 'فروش',
        ]);
        $leaf = Category::factory()->system()->income()->childOf($parent)->create([
            'code' => 'sales-leaf-money',
            'slug' => 'sales-leaf-money',
            'name' => 'فروش کالا',
        ]);

        return [$user, $account, $leaf];
    }
}
