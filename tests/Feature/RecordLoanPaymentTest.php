<?php

namespace Tests\Feature;

use App\Actions\Loans\CreateLoanAction;
use App\Actions\Loans\RecordLoanPaymentAction;
use App\Enums\Accounting\LoanStatus;
use App\Enums\Accounting\LoanType;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecordLoanPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_payment_updates_paid_amount(): void
    {
        [$user, $account, $party, $loan] = $this->prepareLoan(LoanType::Received, '3000');

        $updated = app(RecordLoanPaymentAction::class)->handle($user, $loan, [
            'amount' => '1000',
            'transaction_date' => now()->toDateString(),
            'account_id' => $account->id,
        ]);

        $this->assertSame(0, bccomp((string) $updated->paid_amount, '1000', 18));
        $this->assertSame(LoanStatus::Active, $updated->status);
        $this->assertSame(0, bccomp($updated->remainingAmount(), '2000', 18));

        $account->refresh();
        $this->assertSame(0, bccomp((string) $account->current_balance, '7000', 18));

        $this->assertDatabaseHas('transactions', [
            'loan_id' => $loan->id,
            'type' => TransactionType::Expense->value,
            'amount' => '1000',
        ]);
    }

    public function test_full_payment_completes_loan(): void
    {
        [$user, $account, $party, $loan] = $this->prepareLoan(LoanType::Given, '1500');

        $updated = app(RecordLoanPaymentAction::class)->handle($user, $loan, [
            'amount' => '1500',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertSame(LoanStatus::Completed, $updated->status);
        $this->assertTrue($updated->isFullyPaid());
    }

    public function test_overpayment_is_rejected(): void
    {
        [$user, $account, $party, $loan] = $this->prepareLoan(LoanType::Received, '1000');

        $this->expectException(ValidationException::class);

        app(RecordLoanPaymentAction::class)->handle($user, $loan, [
            'amount' => '1001',
            'transaction_date' => now()->toDateString(),
        ]);
    }

    /**
     * @return array{0: User, 1: Account, 2: Party, 3: Loan}
     */
    protected function prepareLoan(LoanType $type, string $principal): array
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
            'current_balance' => '5000',
            'is_active' => true,
        ]);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $loan = app(CreateLoanAction::class)->handle($user, [
            'type' => $type,
            'party_id' => $party->id,
            'account_id' => $account->id,
            'title' => 'Test loan',
            'principal_amount' => $principal,
            'interest_amount' => '0',
            'issue_date' => now()->toDateString(),
        ]);

        $account->refresh();

        return [$user, $account, $party, $loan];
    }
}
