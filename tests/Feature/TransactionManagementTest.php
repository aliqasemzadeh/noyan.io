<?php

namespace Tests\Feature;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Business;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_income_transaction_with_leaf_category(): void
    {
        [$user, $account, $leaf] = $this->prepareContext();

        Livewire::actingAs($user)
            ->test('accounting.transaction.create')
            ->set('form.type', TransactionType::Income->value)
            ->set('form.account_id', $account->id)
            ->set('form.category_id', $leaf->id)
            ->set('form.amount', '1500')
            ->set('form.transaction_date', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', [
            'business_id' => $account->business_id,
            'account_id' => $account->id,
            'category_id' => $leaf->id,
            'type' => TransactionType::Income->value,
        ]);
    }

    public function test_switching_type_clears_category(): void
    {
        [$user, $account, $leaf] = $this->prepareContext();

        Livewire::actingAs($user)
            ->test('accounting.transaction.create')
            ->set('form.type', TransactionType::Income->value)
            ->set('form.category_id', $leaf->id)
            ->set('form.type', TransactionType::Expense->value)
            ->assertSet('form.category_id', null);
    }

    public function test_transactions_index_is_reachable(): void
    {
        [$user] = $this->prepareContext();

        $this->actingAs($user)
            ->get(route('accounting.transactions.index'))
            ->assertOk();
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
            'code' => 'sales-parent',
            'slug' => 'sales-parent',
            'name' => 'فروش',
        ]);
        $leaf = Category::factory()->system()->income()->childOf($parent)->create([
            'code' => 'sales-leaf',
            'slug' => 'sales-leaf',
            'name' => 'فروش کالا',
        ]);

        return [$user, $account, $leaf];
    }
}
