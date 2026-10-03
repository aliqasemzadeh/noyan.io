<?php

namespace Tests\Feature;

use App\Actions\Cheques\ChangeChequeStatusAction;
use App\Actions\Cheques\DeleteChequeAction;
use App\Actions\Cheques\RegisterChequeAction;
use App\Actions\Cheques\UpdateChequeAction;
use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\ChequeStatus;
use App\Enums\Accounting\ChequeType;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Cheque;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChangeChequeStatusActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_received_cheque_credits_bank_without_changing_party_again(): void
    {
        [$user, $account, $party, $cheque] = $this->registerCheque(ChequeType::Received, '1500', '5000', '8000');

        $partyBalanceAfterRegister = (string) $party->fresh()->balance;

        $updated = app(ChangeChequeStatusAction::class)->handle($user, $cheque, ChequeStatus::Cleared, [
            'transaction_date' => now()->toDateString(),
            'account_id' => $account->id,
        ]);

        $this->assertSame(ChequeStatus::Cleared, $updated->status);
        $this->assertNotNull($updated->cleared_at);

        $party->refresh();
        $account->refresh();

        $this->assertSame(0, bccomp((string) $party->balance, $partyBalanceAfterRegister, 18));
        $this->assertSame(0, bccomp((string) $account->current_balance, '9500', 18));

        $this->assertDatabaseHas('transactions', [
            'cheque_id' => $cheque->id,
            'type' => TransactionType::Income->value,
            'amount' => '1500',
            'party_id' => $party->id,
        ]);
    }

    public function test_clearing_issued_cheque_debits_bank_without_changing_party_again(): void
    {
        [$user, $account, $party, $cheque] = $this->registerCheque(ChequeType::Issued, '700', '2000', '5000');

        $partyBalanceAfterRegister = (string) $party->fresh()->balance;

        app(ChangeChequeStatusAction::class)->handle($user, $cheque, ChequeStatus::Cleared, [
            'transaction_date' => now()->toDateString(),
        ]);

        $party->refresh();
        $account->refresh();

        $this->assertSame(0, bccomp((string) $party->balance, $partyBalanceAfterRegister, 18));
        $this->assertSame(0, bccomp((string) $account->current_balance, '4300', 18));

        $this->assertDatabaseHas('transactions', [
            'cheque_id' => $cheque->id,
            'type' => TransactionType::Expense->value,
            'amount' => '700',
        ]);
    }

    public function test_deposit_only_changes_status(): void
    {
        [$user, $account, $party, $cheque] = $this->registerCheque(ChequeType::Received, '1000', '3000', '4000');

        app(ChangeChequeStatusAction::class)->handle($user, $cheque, ChequeStatus::Deposited);

        $party->refresh();
        $account->refresh();

        $this->assertSame(ChequeStatus::Deposited, $cheque->fresh()->status);
        $this->assertSame(0, bccomp((string) $party->balance, '2000', 18));
        $this->assertSame(0, bccomp((string) $account->current_balance, '4000', 18));
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_bounce_restores_party_balance(): void
    {
        [$user, $account, $party, $cheque] = $this->registerCheque(ChequeType::Received, '1000', '3000', '4000');

        app(ChangeChequeStatusAction::class)->handle($user, $cheque, ChequeStatus::Bounced);

        $party->refresh();
        $account->refresh();
        $cheque->refresh();

        $this->assertSame(ChequeStatus::Bounced, $cheque->status);
        $this->assertNotNull($cheque->party_reversed_at);
        $this->assertSame(0, bccomp((string) $party->balance, '3000', 18));
        $this->assertSame(0, bccomp((string) $account->current_balance, '4000', 18));
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_illegal_transition_is_rejected(): void
    {
        [$user, $account, $party, $cheque] = $this->registerCheque(ChequeType::Issued, '500', '1000', '2000');

        $this->expectException(ValidationException::class);

        app(ChangeChequeStatusAction::class)->handle($user, $cheque, ChequeStatus::Deposited);
    }

    public function test_double_clear_is_rejected(): void
    {
        [$user, $account, $party, $cheque] = $this->registerCheque(ChequeType::Received, '500', '1000', '2000');

        app(ChangeChequeStatusAction::class)->handle($user, $cheque, ChequeStatus::Cleared, [
            'transaction_date' => now()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);

        app(ChangeChequeStatusAction::class)->handle($user, $cheque->fresh(), ChequeStatus::Cleared, [
            'transaction_date' => now()->toDateString(),
        ]);
    }

    public function test_skip_party_balance_flag_on_process_transaction(): void
    {
        [$user, $account, $party] = $this->prepareContext(partyBalance: '1000', accountBalance: '2000');

        app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'party_id' => $party->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '100',
            'skip_party_balance' => true,
        ]);

        $party->refresh();
        $account->refresh();

        $this->assertSame(0, bccomp((string) $party->balance, '1000', 18));
        $this->assertSame(0, bccomp((string) $account->current_balance, '2100', 18));
    }

    public function test_delete_registered_cheque_restores_party_balance(): void
    {
        [$user, $account, $party, $cheque] = $this->registerCheque(ChequeType::Received, '400', '1000', '2000');

        app(DeleteChequeAction::class)->handle($user, $cheque);

        $party->refresh();

        $this->assertSoftDeleted('cheques', ['id' => $cheque->id]);
        $this->assertSame(0, bccomp((string) $party->balance, '1000', 18));
    }

    public function test_update_allows_non_financial_fields_on_registered_cheque(): void
    {
        [$user, $account, $party, $cheque] = $this->registerCheque(ChequeType::Received, '400', '1000', '2000');

        $updated = app(UpdateChequeAction::class)->handle($user, $cheque, [
            'cheque_number' => 'updated-1',
            'bank_name' => 'ملت',
            'due_date' => now()->addDays(20)->toDateString(),
            'note' => 'updated',
        ]);

        $this->assertSame('updated-1', $updated->cheque_number);
        $this->assertSame('ملت', $updated->bank_name);
        $this->assertSame('updated', $updated->note);
        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '600', 18));
    }

    /**
     * @return array{0: User, 1: Account, 2: Party, 3: Cheque}
     */
    protected function registerCheque(
        ChequeType $type,
        string $amount,
        string $partyBalance,
        string $accountBalance,
    ): array {
        [$user, $account, $party] = $this->prepareContext($partyBalance, $accountBalance);

        $cheque = app(RegisterChequeAction::class)->handle($user, [
            'type' => $type,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'cheque_number' => (string) fake()->unique()->numerify('########'),
            'bank_name' => 'ملی',
            'amount' => $amount,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        return [$user, $account->fresh(), $party->fresh(), $cheque];
    }

    /**
     * @return array{0: User, 1: Account, 2: Party}
     */
    protected function prepareContext(string $partyBalance = '0', string $accountBalance = '5000'): array
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
            'current_balance' => $accountBalance,
            'is_active' => true,
        ]);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'balance' => $partyBalance,
            'is_active' => true,
        ]);

        return [$user, $account, $party];
    }
}
