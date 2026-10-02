<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_account_for_current_business(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create([
            'code' => 'BTC',
            'decimal_places' => 8,
        ]);

        Livewire::actingAs($user)
            ->test('accounting.account.create')
            ->set('form.name', 'Cold Wallet')
            ->set('form.currency_id', $currency->id)
            ->set('form.opening_balance', '0.12345678')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('accounting_accounts', [
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'Cold Wallet',
        ]);

        $account = Account::query()->where('name', 'Cold Wallet')->first();
        $this->assertNotNull($account);
        $this->assertSame('0.12345678', rtrim(rtrim((string) $account->opening_balance, '0'), '.'));
    }

    public function test_opening_balance_respects_currency_decimal_places(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
        ]);

        Livewire::actingAs($user)
            ->test('accounting.account.create')
            ->set('form.name', 'Cash')
            ->set('form.currency_id', $currency->id)
            ->set('form.opening_balance', '100.5')
            ->call('save')
            ->assertHasErrors(['form.opening_balance']);
    }

    public function test_accounts_are_scoped_to_current_business(): void
    {
        $user = User::factory()->create();
        $current = Business::factory()->for($user, 'owner')->create(['name' => 'Current']);
        $other = Business::factory()->create(['name' => 'Other']);
        $user->forceFill(['current_business_id' => $current->id])->save();

        $currency = Currency::factory()->create();

        Account::factory()->create([
            'business_id' => $current->id,
            'currency_id' => $currency->id,
            'name' => 'Visible Account',
        ]);

        Account::factory()->create([
            'business_id' => $other->id,
            'currency_id' => $currency->id,
            'name' => 'Hidden Account',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.account.index')
            ->assertSee('Visible Account')
            ->assertDontSee('Hidden Account');
    }

    public function test_user_can_update_and_delete_account(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create(['decimal_places' => 2]);

        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'Bank',
            'opening_balance' => '10.00',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.account.edit')
            ->call('assignData', $account)
            ->set('form.name', 'Main Bank')
            ->set('form.opening_balance', '25.50')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('accounting_accounts', [
            'id' => $account->id,
            'name' => 'Main Bank',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.account.delete')
            ->call('assignData', $account->fresh())
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('accounting_accounts', [
            'id' => $account->id,
        ]);
    }
}
