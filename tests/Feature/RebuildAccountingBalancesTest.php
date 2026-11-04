<?php

namespace Tests\Feature;

use App\Actions\Accounting\RebuildAccountingBalancesAction;
use App\Actions\Transactions\DeleteTransactionAction;
use App\Actions\Transactions\ProcessTransactionAction;
use App\Actions\Transactions\UpdateTransactionAction;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use App\Support\AccountValidationRules;
use App\Support\Accounting\AccountLedgerBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RebuildAccountingBalancesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_persists_balance_after_and_account_current_balance(): void
    {
        [$user, $account, $other] = $this->prepareAccounts(opening: '1000');

        $income = Transaction::factory()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'type' => TransactionType::Income,
            'amount' => '500',
            'base_amount' => '500',
            'currency' => 'IRR',
            'transaction_date' => '2026-01-01',
        ]);

        $expense = Transaction::factory()->expense()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'amount' => '200',
            'base_amount' => '200',
            'currency' => 'IRR',
            'transaction_date' => '2026-01-02',
        ]);

        $transferOut = Transaction::factory()->transfer()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'destination_account_id' => $other->id,
            'amount' => '100',
            'base_amount' => '100',
            'currency' => 'IRR',
            'transaction_date' => '2026-01-03',
        ]);

        $transferIn = Transaction::factory()->transfer()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'account_id' => $other->id,
            'destination_account_id' => $account->id,
            'amount' => '50',
            'base_amount' => '50',
            'currency' => 'IRR',
            'transaction_date' => '2026-01-04',
        ]);

        $account->forceFill(['current_balance' => '9999'])->save();

        app(RebuildAccountingBalancesAction::class)->handle(collect([$account->business]));

        $this->assertSame('1500', AccountValidationRules::normalizeBalance((string) $income->fresh()->balance_after));
        $this->assertSame('1300', AccountValidationRules::normalizeBalance((string) $expense->fresh()->balance_after));
        $this->assertSame('1200', AccountValidationRules::normalizeBalance((string) $transferOut->fresh()->balance_after));
        $this->assertSame('1100', AccountValidationRules::normalizeBalance((string) $transferOut->fresh()->destination_balance_after));
        $this->assertSame('1050', AccountValidationRules::normalizeBalance((string) $transferIn->fresh()->balance_after));
        $this->assertSame('1250', AccountValidationRules::normalizeBalance((string) $transferIn->fresh()->destination_balance_after));
        $this->assertSame(
            '1250',
            AccountValidationRules::normalizeBalance((string) $account->fresh()->current_balance)
        );
        $this->assertSame(
            '1050',
            AccountValidationRules::normalizeBalance((string) $other->fresh()->current_balance)
        );
    }

    public function test_process_transaction_persists_balance_after(): void
    {
        [$user, $account] = $this->prepareAccounts(opening: '1000');
        $account->forceFill(['current_balance' => '1000'])->save();

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '250',
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'skip_auto_post' => true,
        ]);

        $this->assertSame('1250', AccountValidationRules::normalizeBalance((string) $transaction->fresh()->balance_after));
        $this->assertSame(
            '1250',
            AccountValidationRules::normalizeBalance((string) $account->fresh()->current_balance)
        );
    }

    public function test_update_and_delete_keep_ledger_balances_in_sync(): void
    {
        [$user, $account] = $this->prepareAccounts(opening: '1000');
        $account->forceFill(['current_balance' => '1000'])->save();

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '250',
            'currency' => 'IRR',
            'exchange_rate' => '1',
            'skip_auto_post' => true,
        ]);

        app(UpdateTransactionAction::class)->handle($user, $transaction, [
            'type' => TransactionType::Income,
            'account_id' => $account->id,
            'transaction_date' => now()->toDateString(),
            'amount' => '400',
            'currency' => 'IRR',
            'exchange_rate' => '1',
        ]);

        $this->assertSame('1400', AccountValidationRules::normalizeBalance((string) $transaction->fresh()->balance_after));
        $this->assertSame(
            '1400',
            AccountValidationRules::normalizeBalance((string) $account->fresh()->current_balance)
        );

        app(DeleteTransactionAction::class)->handle($user, $transaction->fresh());

        $this->assertSame(
            '1000',
            AccountValidationRules::normalizeBalance((string) $account->fresh()->current_balance)
        );
    }

    public function test_attach_prefers_stored_balance_after(): void
    {
        [$user, $account] = $this->prepareAccounts(opening: '1000');

        $transaction = Transaction::factory()->create([
            'business_id' => $account->business_id,
            'created_by' => $user->id,
            'account_id' => $account->id,
            'type' => TransactionType::Income,
            'amount' => '100',
            'base_amount' => '100',
            'currency' => 'IRR',
            'balance_after' => '4242',
        ]);

        AccountLedgerBalance::attachForAccount($account, collect([$transaction]));

        $this->assertSame('4242', AccountValidationRules::normalizeBalance((string) $transaction->balance_after));
    }

    /**
     * @return array{0: User, 1: Account, 2: Account}
     */
    protected function prepareAccounts(string $opening = '0'): array
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
            'opening_balance' => $opening,
            'current_balance' => $opening,
        ]);

        $other = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'opening_balance' => '1000',
            'current_balance' => '1000',
        ]);

        return [$user, $account, $other];
    }
}
