<?php

namespace Tests\Feature;

use App\Actions\Cheques\RegisterChequeAction;
use App\Enums\Accounting\ChequeStatus;
use App\Enums\Accounting\ChequeType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Party;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegisterChequeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_received_cheque_decreases_party_balance_without_bank_or_transaction(): void
    {
        [$user, $account, $party] = $this->prepareContext(partyBalance: '5000', accountBalance: '8000');

        $cheque = app(RegisterChequeAction::class)->handle($user, [
            'type' => ChequeType::Received,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'cheque_number' => '123456',
            'bank_name' => 'ملی',
            'amount' => '1500',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->assertSame(ChequeStatus::Registered, $cheque->status);
        $this->assertSame(ChequeType::Received, $cheque->type);

        $party->refresh();
        $account->refresh();

        $this->assertSame(0, bccomp((string) $party->balance, '3500', 18));
        $this->assertSame(0, bccomp((string) $account->current_balance, '8000', 18));
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_issued_cheque_increases_party_balance(): void
    {
        [$user, $account, $party] = $this->prepareContext(partyBalance: '2000');

        app(RegisterChequeAction::class)->handle($user, [
            'type' => ChequeType::Issued,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'cheque_number' => '654321',
            'bank_name' => 'ملت',
            'amount' => '700',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $party->refresh();
        $this->assertSame(0, bccomp((string) $party->balance, '2700', 18));
    }

    public function test_rejects_non_positive_amount(): void
    {
        [$user, $account, $party] = $this->prepareContext();

        $this->expectException(ValidationException::class);

        app(RegisterChequeAction::class)->handle($user, [
            'type' => ChequeType::Received,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'cheque_number' => '1',
            'bank_name' => 'ملی',
            'amount' => '0',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);
    }

    public function test_rejects_due_date_before_issue_date(): void
    {
        [$user, $account, $party] = $this->prepareContext();

        $this->expectException(ValidationException::class);

        app(RegisterChequeAction::class)->handle($user, [
            'type' => ChequeType::Received,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'cheque_number' => '1',
            'bank_name' => 'ملی',
            'amount' => '100',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
        ]);
    }

    public function test_rejects_duplicate_cheque_number_for_same_bank_and_type(): void
    {
        [$user, $account, $party] = $this->prepareContext();

        $payload = [
            'type' => ChequeType::Received,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'cheque_number' => '999',
            'bank_name' => 'ملی',
            'amount' => '100',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ];

        app(RegisterChequeAction::class)->handle($user, $payload);

        $this->expectException(ValidationException::class);

        app(RegisterChequeAction::class)->handle($user, $payload);
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
