<?php

namespace Tests\Feature;

use App\Enums\AccountSubType;
use App\Enums\AccountType;
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

    public function test_user_can_create_cash_account_without_bank_fields(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
        ]);
        $business->activateCurrency($currency, '1');

        Livewire::actingAs($user)
            ->test('accounting.account.create')
            ->set('form.name', 'Store Cash')
            ->set('form.sub_type', AccountSubType::Cash->value)
            ->set('form.currency_id', $currency->id)
            ->set('form.opening_balance', '1000')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('accounting_accounts', [
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'Store Cash',
            'type' => AccountType::Asset->value,
            'sub_type' => AccountSubType::Cash->value,
            'bank_name' => null,
            'account_number' => null,
            'card_number' => null,
            'iban' => null,
        ]);
    }

    public function test_user_can_create_bank_account_with_optional_bank_fields(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
        ]);
        $business->activateCurrency($currency, '1');

        Livewire::actingAs($user)
            ->test('accounting.account.create')
            ->set('form.name', 'Mellat POS')
            ->set('form.sub_type', AccountSubType::Bank->value)
            ->set('form.currency_id', $currency->id)
            ->set('form.bank_name', 'Bank Mellat')
            ->set('form.account_number', '0100324200001')
            ->set('form.card_number', '6037997599422129')
            ->set('form.iban', 'IR062960000000100324200001')
            ->set('form.opening_balance', '0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('accounting_accounts', [
            'business_id' => $business->id,
            'name' => 'Mellat POS',
            'sub_type' => AccountSubType::Bank->value,
            'bank_name' => 'Bank Mellat',
            'account_number' => '0100324200001',
            'card_number' => '6037997599422129',
            'iban' => 'IR062960000000100324200001',
        ]);
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
        $business->activateCurrency($currency, '1');

        Livewire::actingAs($user)
            ->test('accounting.account.create')
            ->set('form.name', 'Cash')
            ->set('form.sub_type', AccountSubType::Cash->value)
            ->set('form.currency_id', $currency->id)
            ->set('form.opening_balance', '100.5')
            ->call('save')
            ->assertHasErrors(['form.opening_balance']);
    }

    public function test_account_rejects_non_activated_currency(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create(['code' => 'EUR']);

        Livewire::actingAs($user)
            ->test('accounting.account.create')
            ->set('form.name', 'Euro Account')
            ->set('form.sub_type', AccountSubType::Cash->value)
            ->set('form.currency_id', $currency->id)
            ->set('form.opening_balance', '100')
            ->call('save')
            ->assertHasErrors(['form.currency_id']);
    }

    public function test_accounts_are_scoped_to_current_business(): void
    {
        $user = User::factory()->create();
        $current = Business::factory()->for($user, 'owner')->create(['name' => 'Current']);
        $other = Business::factory()->create(['name' => 'Other']);
        $user->forceFill(['current_business_id' => $current->id])->save();

        $currency = Currency::factory()->create();
        $current->activateCurrency($currency, '1');

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

    public function test_user_can_view_account_for_current_business(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create(['decimal_places' => 0]);
        $business->activateCurrency($currency, '1');

        $account = Account::factory()->bank()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'Main Bank',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.account.view', ['account' => $account])
            ->assertSee('Main Bank')
            ->assertSee('Bank Melli')
            ->assertSee('IR062960000000100324200001')
            ->assertSee('6037997599422129');
    }

    public function test_user_cannot_view_account_from_another_business(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $other = Business::factory()->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create();

        $account = Account::factory()->create([
            'business_id' => $other->id,
            'currency_id' => $currency->id,
            'name' => 'Foreign Account',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.account.view', ['account' => $account])
            ->assertForbidden();
    }

    public function test_user_can_update_and_delete_account(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create(['decimal_places' => 2]);
        $business->activateCurrency($currency, '1');

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
            ->set('form.sub_type', AccountSubType::Bank->value)
            ->set('form.account_number', '1234567890')
            ->set('form.iban', 'IR580540105180021273113007')
            ->set('form.note', 'Payroll account')
            ->set('form.opening_balance', '25.50')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('accounting_accounts', [
            'id' => $account->id,
            'name' => 'Main Bank',
            'sub_type' => AccountSubType::Bank->value,
            'account_number' => '1234567890',
            'iban' => 'IR580540105180021273113007',
            'note' => 'Payroll account',
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
