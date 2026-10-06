<?php

namespace Tests\Feature;

use App\Ai\Agents\AccountingAssistant;
use App\Ai\Tools\CreateBankAccount;
use App\Ai\Tools\CreateTransaction;
use App\Enums\AccountSubType;
use App\Enums\AccountType;
use App\Models\Accounting\Account;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Middleware\RememberConversation;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingAiBankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_creates_bank_account_from_sms_fields(): void
    {
        [$user, $business, $currency] = $this->actingBusinessOwnerWithCurrency();

        $result = (string) (new CreateBankAccount)->handle(new Request([
            'name' => 'حساب جاری ملت',
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'balance' => '644,031,211',
        ]));

        $this->assertSame(__('general.ai_account_created', [
            'name' => 'حساب جاری ملت',
            'bank' => 'ملت',
            'number' => '5199858647',
            'balance' => '644031211',
        ]), $result);

        $this->assertDatabaseHas('accounting_accounts', [
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'حساب جاری ملت',
            'type' => AccountType::Asset->value,
            'sub_type' => AccountSubType::Bank->value,
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'opening_balance' => '644031211',
            'current_balance' => '644031211',
        ]);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tool_strips_commas_and_persian_digits(): void
    {
        [$user, $business, $currency] = $this->actingBusinessOwnerWithCurrency();

        (new CreateBankAccount)->handle(new Request([
            'name' => 'حساب جاری',
            'bank_name' => 'ملی',
            'account_number' => '۵۱۹۹۸۵۸۶۴۷',
            'balance' => '۶۴۴,۰۳۱,۲۱۱',
        ]));

        $this->assertDatabaseHas('accounting_accounts', [
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'account_number' => '5199858647',
            'opening_balance' => '644031211',
            'current_balance' => '644031211',
        ]);
    }

    public function test_tool_defaults_to_base_currency_when_code_missing(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $irr = Currency::factory()->create(['code' => 'IRR', 'decimal_places' => 0]);
        $irt = Currency::factory()->create(['code' => 'IRT', 'decimal_places' => 0]);
        $business->activateCurrency($irr, '1', true);
        $business->activateCurrency($irt, '1', false)->setAsBase();

        $this->actingAs($user);

        (new CreateBankAccount)->handle(new Request([
            'name' => 'حساب جاری',
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'balance' => '1000',
        ]));

        $this->assertDatabaseHas('accounting_accounts', [
            'business_id' => $business->id,
            'currency_id' => $irt->id,
            'account_number' => '5199858647',
        ]);
    }

    public function test_tool_uses_explicit_currency_code(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $irr = Currency::factory()->create(['code' => 'IRR', 'decimal_places' => 0]);
        $irt = Currency::factory()->create(['code' => 'IRT', 'decimal_places' => 0]);
        $business->activateCurrency($irr, '1', true);
        $business->activateCurrency($irt, '1', false);

        $this->actingAs($user);

        (new CreateBankAccount)->handle(new Request([
            'name' => 'حساب جاری',
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'balance' => '1000',
            'currency_code' => 'IRT',
        ]));

        $this->assertDatabaseHas('accounting_accounts', [
            'business_id' => $business->id,
            'currency_id' => $irt->id,
            'account_number' => '5199858647',
        ]);
    }

    public function test_tool_returns_missing_fields_message_without_creating(): void
    {
        [$user] = $this->actingBusinessOwnerWithCurrency();

        $result = (string) (new CreateBankAccount)->handle(new Request([
            'name' => '',
            'bank_name' => '',
            'account_number' => '5199858647',
            'balance' => '644031211',
        ]));

        $this->assertSame(__('general.ai_account_missing_fields', [
            'fields' => __('general.name').'، '.__('general.bank_name'),
        ]), $result);

        $this->assertDatabaseCount('accounting_accounts', 0);
    }

    public function test_tool_rejects_duplicate_account_number_in_business(): void
    {
        [$user, $business, $currency] = $this->actingBusinessOwnerWithCurrency();

        Account::factory()->bank()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'account_number' => '5199858647',
        ]);

        $result = (string) (new CreateBankAccount)->handle(new Request([
            'name' => 'حساب جدید',
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'balance' => '1000',
        ]));

        $this->assertSame(__('general.ai_account_already_exists', [
            'number' => '5199858647',
        ]), $result);

        $this->assertSame(1, Account::query()
            ->where('business_id', $business->id)
            ->where('account_number', '5199858647')
            ->count());
    }

    public function test_tool_allows_same_account_number_in_other_business(): void
    {
        [$user, $business, $currency] = $this->actingBusinessOwnerWithCurrency();

        $otherBusiness = Business::factory()->create();
        Account::factory()->bank()->create([
            'business_id' => $otherBusiness->id,
            'currency_id' => $currency->id,
            'account_number' => '5199858647',
        ]);

        (new CreateBankAccount)->handle(new Request([
            'name' => 'حساب جاری',
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'balance' => '1000',
        ]));

        $this->assertDatabaseHas('accounting_accounts', [
            'business_id' => $business->id,
            'account_number' => '5199858647',
        ]);
    }

    public function test_tool_fails_without_business(): void
    {
        $user = User::factory()->create([
            'current_business_id' => null,
        ]);
        $this->actingAs($user);

        $result = (string) (new CreateBankAccount)->handle(new Request([
            'name' => 'حساب جاری',
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'balance' => '1000',
        ]));

        $this->assertSame(__('general.business_required'), $result);
        $this->assertDatabaseCount('accounting_accounts', 0);
    }

    public function test_tool_returns_invalid_message_for_bad_balance(): void
    {
        [$user] = $this->actingBusinessOwnerWithCurrency();

        $result = (string) (new CreateBankAccount)->handle(new Request([
            'name' => 'حساب جاری',
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'balance' => 'abc',
        ]));

        $this->assertStringStartsWith(
            str_replace(':errors', '', __('general.ai_account_invalid', ['errors' => ':errors'])),
            $result,
        );
        $this->assertDatabaseCount('accounting_accounts', 0);
    }

    public function test_tool_forgets_account_options_cache(): void
    {
        [$user, $business] = $this->actingBusinessOwnerWithCurrency();

        Account::cachedOptionsForBusiness($business->id);
        $this->assertTrue(Cache::has(Account::optionsCacheKey($business->id)));

        (new CreateBankAccount)->handle(new Request([
            'name' => 'حساب جاری ملت',
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'balance' => '1000',
        ]));

        $options = Account::cachedOptionsForBusiness($business->id);

        $this->assertTrue($options->contains(fn (Account $account): bool => $account->name === 'حساب جاری ملت'));
    }

    public function test_accounting_assistant_registers_tool_and_remembers_conversations(): void
    {
        $assistant = new AccountingAssistant;

        $tools = [...$assistant->tools()];

        $this->assertCount(2, $tools);
        $this->assertInstanceOf(CreateBankAccount::class, $tools[0]);
        $this->assertInstanceOf(CreateTransaction::class, $tools[1]);
        $this->assertTrue(RememberConversation::appliesTo($assistant));
    }

    public function test_accounting_assistant_lists_existing_accounts_in_instructions(): void
    {
        [$user, $business, $currency] = $this->actingBusinessOwnerWithCurrency();

        Account::factory()->bank()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'حساب جاری ملت',
            'bank_name' => 'ملت',
            'account_number' => '5199858647',
            'current_balance' => '12500000',
        ]);

        $instructions = (string) (new AccountingAssistant)->instructions();

        $this->assertStringContainsString('5199858647', $instructions);
        $this->assertStringContainsString('حساب جاری ملت', $instructions);
        $this->assertStringContainsString('12,500,000', $instructions);
        $this->assertStringContainsString('مانده/موجودی', $instructions);
        $this->assertStringContainsString('create_transaction', $instructions);
    }

    public function test_empty_prompt_rejected_for_accounting_context(): void
    {
        [$user] = $this->actingBusinessOwnerWithCurrency();

        Livewire::actingAs($user)
            ->test('ai.composer', ['context' => 'accounting'])
            ->set('prompt', '')
            ->call('sendPrompt')
            ->assertHasErrors(['prompt']);
    }

    public function test_composer_accepts_prompt_up_to_2000_characters(): void
    {
        AccountingAssistant::fake(['پاسخ تست']);

        [$user] = $this->actingBusinessOwnerWithCurrency();

        Livewire::actingAs($user)
            ->test('ai.composer', ['context' => 'accounting'])
            ->set('prompt', str_repeat('ا', 2000))
            ->call('sendPrompt')
            ->assertHasNoErrors()
            ->assertSet('assistantReply', 'پاسخ تست');

        Livewire::actingAs($user)
            ->test('ai.composer', ['context' => 'accounting'])
            ->set('prompt', str_repeat('ا', 2001))
            ->call('sendPrompt')
            ->assertHasErrors(['prompt']);
    }

    public function test_accounting_prompt_dispatches_account_table_event(): void
    {
        AccountingAssistant::fake([__('general.ai_account_created', [
            'name' => 'حساب جاری',
            'bank' => 'ملت',
            'number' => '5199858647',
            'balance' => '644031211',
        ])]);

        [$user] = $this->actingBusinessOwnerWithCurrency();

        $sms = "حساب5199858647\nمانده644,031,211\nبانک ملت، حساب جاری";

        Livewire::actingAs($user)
            ->test('ai.composer', ['context' => 'accounting'])
            ->set('prompt', $sms)
            ->call('sendPrompt')
            ->assertHasNoErrors()
            ->assertSet('assistantReply', __('general.ai_account_created', [
                'name' => 'حساب جاری',
                'bank' => 'ملت',
                'number' => '5199858647',
                'balance' => '644031211',
            ]))
            ->assertSet('prompt', '')
            ->assertDispatched('panels.accounting.account.index.table')
            ->assertDispatched('panels.accounting.transaction.index.table');

        AccountingAssistant::assertPrompted($sms);
    }

    public function test_composer_continues_conversation_across_turns(): void
    {
        AccountingAssistant::fake([
            'نام بانک و یک نام نمایشی برای حساب را بفرمایید.',
            __('general.ai_account_created', [
                'name' => 'حساب جاری',
                'bank' => 'ملت',
                'number' => '5199858647',
                'balance' => '644031211',
            ]),
        ]);

        [$user] = $this->actingBusinessOwnerWithCurrency();

        $component = Livewire::actingAs($user)
            ->test('ai.composer', ['context' => 'accounting'])
            ->set('prompt', "حساب5199858647\nمانده644,031,211")
            ->call('sendPrompt')
            ->assertHasNoErrors()
            ->assertSet('assistantReply', 'نام بانک و یک نام نمایشی برای حساب را بفرمایید.');

        $conversationId = $component->get('conversationId');

        $this->assertNotNull($conversationId);

        $component
            ->set('prompt', 'ملت، حساب جاری')
            ->call('sendPrompt')
            ->assertHasNoErrors()
            ->assertSet('conversationId', $conversationId);

        AccountingAssistant::assertPromptedTimes(2);
        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversationId,
        ]);
    }

    /**
     * @return array{0: User, 1: Business, 2: Currency}
     */
    protected function actingBusinessOwnerWithCurrency(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
        ]);
        $business->activateCurrency($currency, '1', true);
        $this->actingAs($user);

        return [$user, $business, $currency];
    }
}
