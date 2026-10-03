<?php

namespace Tests\Feature;

use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\TransactionType;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Business;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcessTransactionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_transaction_persists_leaf_category(): void
    {
        [$user, $account, $leaf] = $this->prepareTransactionContext(CategoryType::Income);

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'category_id' => $leaf->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '1000',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $this->assertSame($leaf->id, $transaction->category_id);
    }

    public function test_non_leaf_category_is_rejected(): void
    {
        [$user, $account, $leaf] = $this->prepareTransactionContext(CategoryType::Expense);
        $parent = $leaf->parent;

        $this->expectException(ValidationException::class);

        app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Expense,
            'account_id' => $account->id,
            'category_id' => $parent->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '500',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);
    }

    public function test_mismatched_category_type_is_rejected(): void
    {
        [$user, $account] = $this->prepareTransactionContext(CategoryType::Income);
        $expenseLeaf = Category::factory()->system()->expense()->create([
            'code' => 'rent-leaf',
            'slug' => 'rent-leaf',
        ]);

        $this->expectException(ValidationException::class);

        app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'category_id' => $expenseLeaf->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '500',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);
    }

    public function test_transfer_ignores_category(): void
    {
        [$user, $account, $leaf] = $this->prepareTransactionContext(CategoryType::Income);
        $destination = Account::factory()->create([
            'business_id' => $account->business_id,
            'currency_id' => $account->currency_id,
        ]);

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Transfer,
            'account_id' => $account->id,
            'destination_account_id' => $destination->id,
            'category_id' => $leaf->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '250',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $this->assertNull($transaction->category_id);
    }

    /**
     * @return array{0: User, 1: Account, 2: Category}
     */
    protected function prepareTransactionContext(CategoryType $type): array
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
        ]);

        $parent = Category::factory()->system()->create([
            'type' => $type,
            'code' => $type->value.'-parent',
            'slug' => $type->value.'-parent',
            'name' => 'Parent',
        ]);
        $leaf = Category::factory()->system()->childOf($parent)->create([
            'code' => $type->value.'-leaf',
            'slug' => $type->value.'-leaf',
            'name' => 'Leaf',
        ]);

        return [$user, $account, $leaf];
    }
}
