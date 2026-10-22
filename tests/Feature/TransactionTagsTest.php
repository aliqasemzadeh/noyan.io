<?php

namespace Tests\Feature;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use App\Support\LocaleDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_create_syncs_tags_for_current_business(): void
    {
        [$user, $account, $leaf] = $this->prepareContext();

        App::setLocale('fa');
        $jalaliToday = LocaleDate::formatInput(now());

        Livewire::actingAs($user)
            ->test('accounting.transaction.create')
            ->set('form.type', TransactionType::Expense->value)
            ->set('form.account_id', $account->id)
            ->set('form.category_id', $leaf->id)
            ->set('form.amount', '250000')
            ->set('form.transaction_date', $jalaliToday)
            ->set('form.tag_names', ['آلا', 'محمد'])
            ->call('save')
            ->assertHasNoErrors();

        $transaction = Transaction::query()->first();

        $this->assertNotNull($transaction);
        $this->assertEqualsCanonicalizing(
            ['آلا', 'محمد'],
            $transaction->tags->map(fn ($tag) => (string) $tag->name)->all()
        );
        $this->assertTrue(
            $transaction->tags->every(
                fn ($tag) => $tag->type === Transaction::tagTypeForBusiness((int) $account->business_id)
            )
        );
    }

    public function test_transaction_edit_syncs_tags(): void
    {
        [$user, $account] = $this->prepareContext();

        $transaction = Transaction::factory()->expense()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'amount' => '1000',
            'base_amount' => '1000',
        ]);
        $transaction->syncTagsWithType(['آلا'], Transaction::tagTypeForBusiness((int) $account->business_id));

        App::setLocale('fa');

        Livewire::actingAs($user)
            ->test('accounting.transaction.edit')
            ->call('assignData', $transaction)
            ->set('form.amount', '1000')
            ->set('form.transaction_date', LocaleDate::formatInput($transaction->transaction_date))
            ->set('form.tag_names', ['محمد'])
            ->call('save')
            ->assertHasNoErrors();

        $transaction->refresh();
        $transaction->load('tags');

        $this->assertEqualsCanonicalizing(
            ['محمد'],
            $transaction->tags->map(fn ($tag) => (string) $tag->name)->all()
        );
    }

    public function test_transaction_index_filters_by_tag(): void
    {
        [$user, $account] = $this->prepareContext();
        $tagType = Transaction::tagTypeForBusiness((int) $account->business_id);

        $tagged = Transaction::factory()->expense()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'amount' => '5000',
            'base_amount' => '5000',
            'reference_number' => 'TAGGED-ALA',
        ]);
        $tagged->syncTagsWithType(['آلا'], $tagType);

        Transaction::factory()->expense()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'amount' => '7000',
            'base_amount' => '7000',
            'reference_number' => 'UNTAGGED-REF',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.transaction.index')
            ->assertSee('TAGGED-ALA')
            ->assertSee('UNTAGGED-REF')
            ->set('tagFilter', 'آلا')
            ->assertSee('TAGGED-ALA')
            ->assertDontSee('UNTAGGED-REF');
    }

    public function test_tag_names_are_scoped_per_business(): void
    {
        [$user, $account] = $this->prepareContext();

        $otherUser = User::factory()->create();
        $otherBusiness = Business::factory()->for($otherUser, 'owner')->create();
        $otherUser->forceFill(['current_business_id' => $otherBusiness->id])->save();

        $transaction = Transaction::factory()->expense()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'account_id' => $account->id,
        ]);
        $transaction->syncTagsWithType(['آلا'], Transaction::tagTypeForBusiness((int) $account->business_id));

        $this->assertTrue(
            Transaction::tagNamesForBusiness((int) $account->business_id)->contains('آلا')
        );
        $this->assertFalse(
            Transaction::tagNamesForBusiness((int) $otherBusiness->id)->contains('آلا')
        );
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
            'current_balance' => '1000000',
            'is_active' => true,
        ]);

        $parent = Category::factory()->system()->expense()->create([
            'code' => 'expense-parent-tags',
            'slug' => 'expense-parent-tags',
            'name' => 'هزینه‌ها',
        ]);
        $leaf = Category::factory()->system()->expense()->childOf($parent)->create([
            'code' => 'expense-leaf-tags',
            'slug' => 'expense-leaf-tags',
            'name' => 'هزینه مدرسه',
        ]);

        return [$user, $account, $leaf];
    }
}
