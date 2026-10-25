<?php

namespace Tests\Feature;

use App\Ai\Tools\CreateTransaction;
use App\Enums\Accounting\TransactionType;
use App\Enums\AccountSubType;
use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\Accounting\Account;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingAiPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_without_transaction_create_cannot_use_create_transaction_tool(): void
    {
        [$member] = $this->actingMemberWithPermissions([
            BusinessPermission::TransactionView->value,
            BusinessPermission::AccountView->value,
        ]);

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'type' => 'expense',
            'amount' => '1000',
            'account_number' => '5199858647',
            'transaction_date' => now()->toDateString(),
        ]));

        $this->assertSame(__('general.ai_permission_denied'), $result);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_member_with_transaction_create_can_use_create_transaction_tool(): void
    {
        [$member, $business, $account] = $this->actingMemberWithPermissions([
            BusinessPermission::TransactionCreate->value,
            BusinessPermission::TransactionView->value,
            BusinessPermission::AccountView->value,
        ]);

        $result = (string) (new CreateTransaction)->handle(new Request([
            'confirmed' => true,
            'type' => 'expense',
            'amount' => '1500',
            'account_number' => '5199858647',
            'transaction_date' => now()->toDateString(),
            'note' => 'برداشت',
        ]));

        $this->assertStringContainsString(__('general.ai_transaction_created', [
            'type' => TransactionType::Expense->label(),
            'amount' => '1500',
            'account' => $account->name,
            'date' => \App\Support\LocaleDate::formatDate(now()->toDateString()),
        ]), $result);

        $this->assertDatabaseHas('transactions', [
            'business_id' => $business->id,
            'account_id' => $account->id,
            'amount' => '1500',
        ]);
    }

    public function test_viewer_without_accounting_panel_cannot_open_ai_composer(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        BusinessUser::factory()
            ->withPermissions([
                BusinessPermission::InvoiceView->value,
                BusinessPermission::PartyView->value,
            ], BusinessRole::Seller)
            ->for($business)
            ->for($member, 'user')
            ->create();

        $member->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($member)
            ->test('ai.composer', ['context' => 'accounting'])
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: User, 1: Business, 2: Account}
     */
    protected function actingMemberWithPermissions(array $permissions): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        BusinessUser::factory()
            ->withPermissions($permissions, BusinessRole::Accountant)
            ->for($business)
            ->for($member, 'user')
            ->create();

        $member->forceFill(['current_business_id' => $business->id])->save();

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
            'opening_balance' => '100000',
            'current_balance' => '100000',
        ]);

        $this->actingAs($member);

        return [$member->fresh(), $business, $account];
    }
}
