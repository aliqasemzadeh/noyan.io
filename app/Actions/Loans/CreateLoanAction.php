<?php

namespace App\Actions\Loans;

use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\LoanStatus;
use App\Enums\Accounting\LoanType;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreateLoanAction
{
    public function __construct(
        private ProcessTransactionAction $processTransaction,
    ) {}

    /**
     * @param  array{
     *     party_id: int,
     *     account_id: int,
     *     type: string|LoanType,
     *     title: string,
     *     principal_amount: string|float|int,
     *     interest_amount?: string|float|int|null,
     *     installments_count?: int|null,
     *     installment_amount?: string|float|int|null,
     *     issue_date: string,
     *     first_installment_date?: string|null,
     *     description?: string|null
     * }  $data
     */
    public function handle(User $user, array $data): Loan
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to create a loan.');
        }

        return DB::transaction(function () use ($user, $data, $businessId): Loan {
            $type = $data['type'] instanceof LoanType
                ? $data['type']
                : LoanType::from((string) $data['type']);

            $principal = $this->normalizeDecimal((string) $data['principal_amount']);
            $interest = $this->normalizeDecimal((string) ($data['interest_amount'] ?? '0'));

            if (bccomp($principal, '0', 18) !== 1) {
                throw ValidationException::withMessages([
                    'principal_amount' => [__('general.loan_principal_must_be_positive')],
                ]);
            }

            if (bccomp($interest, '0', 18) === -1) {
                throw ValidationException::withMessages([
                    'interest_amount' => [__('general.loan_interest_must_be_non_negative')],
                ]);
            }

            $total = bcadd($principal, $interest, 18);

            $party = Party::query()
                ->where('business_id', $businessId)
                ->whereKey($data['party_id'])
                ->lockForUpdate()
                ->first();

            if ($party === null) {
                throw ValidationException::withMessages([
                    'party_id' => [__('general.loan_party_invalid')],
                ]);
            }

            $account = Account::query()
                ->where('business_id', $businessId)
                ->whereKey($data['account_id'])
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                throw ValidationException::withMessages([
                    'account_id' => [__('general.loan_account_invalid')],
                ]);
            }

            $installmentAmount = array_key_exists('installment_amount', $data) && $data['installment_amount'] !== null && $data['installment_amount'] !== ''
                ? $this->normalizeDecimal((string) $data['installment_amount'])
                : null;

            $loan = Loan::query()->create([
                'business_id' => $businessId,
                'party_id' => $party->id,
                'account_id' => $account->id,
                'type' => $type,
                'title' => $data['title'],
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'total_amount' => $total,
                'paid_amount' => '0',
                'installments_count' => $data['installments_count'] ?? null,
                'installment_amount' => $installmentAmount,
                'issue_date' => $data['issue_date'],
                'first_installment_date' => $data['first_installment_date'] ?? null,
                'status' => LoanStatus::Active,
                'description' => $data['description'] ?? null,
            ]);

            $transactionType = $type === LoanType::Received
                ? TransactionType::Income
                : TransactionType::Expense;

            $this->processTransaction->handle($user, [
                'type' => $transactionType,
                'account_id' => $account->id,
                'party_id' => $party->id,
                'loan_id' => $loan->id,
                'transaction_date' => $data['issue_date'],
                'amount' => $principal,
                'note' => $loan->title,
            ]);

            return $loan->fresh(['party', 'account', 'transactions']);
        });
    }

    protected function normalizeDecimal(string $amount): string
    {
        $amount = trim($amount);

        if ($amount === '' || ! is_numeric($amount)) {
            return '0';
        }

        if (! str_contains($amount, '.')) {
            return $amount;
        }

        return rtrim(rtrim($amount, '0'), '.') ?: '0';
    }
}
