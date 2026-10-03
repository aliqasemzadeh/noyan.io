<?php

namespace App\Actions\Loans;

use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\LoanStatus;
use App\Enums\Accounting\LoanType;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RecordLoanPaymentAction
{
    public function __construct(
        private ProcessTransactionAction $processTransaction,
    ) {}

    /**
     * @param  array{
     *     amount: string|float|int,
     *     transaction_date: string,
     *     account_id?: int|null,
     *     reference_number?: string|null,
     *     note?: string|null
     * }  $data
     */
    public function handle(User $user, Loan $loan, array $data): Loan
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to record a loan payment.');
        }

        return DB::transaction(function () use ($user, $loan, $data, $businessId): Loan {
            $lockedLoan = Loan::query()
                ->where('business_id', $businessId)
                ->whereKey($loan->id)
                ->lockForUpdate()
                ->first();

            if ($lockedLoan === null) {
                throw ValidationException::withMessages([
                    'loan_id' => [__('general.loan_not_found')],
                ]);
            }

            if ($lockedLoan->status !== LoanStatus::Active) {
                throw ValidationException::withMessages([
                    'loan_id' => [__('general.loan_not_active')],
                ]);
            }

            $amount = $this->normalizeDecimal((string) $data['amount']);

            if (bccomp($amount, '0', 18) !== 1) {
                throw ValidationException::withMessages([
                    'amount' => [__('general.loan_payment_must_be_positive')],
                ]);
            }

            $remaining = $lockedLoan->remainingAmount();

            if (bccomp($amount, $remaining, 18) === 1) {
                throw ValidationException::withMessages([
                    'amount' => [__('general.loan_payment_exceeds_remaining')],
                ]);
            }

            $accountId = $data['account_id'] ?? $lockedLoan->account_id;

            $transactionType = $lockedLoan->type === LoanType::Received
                ? TransactionType::Expense
                : TransactionType::Income;

            $this->processTransaction->handle($user, [
                'type' => $transactionType,
                'account_id' => $accountId,
                'party_id' => $lockedLoan->party_id,
                'loan_id' => $lockedLoan->id,
                'transaction_date' => $data['transaction_date'],
                'amount' => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'note' => $data['note'] ?? $lockedLoan->title,
            ]);

            $paidAmount = bcadd((string) $lockedLoan->paid_amount, $amount, 18);
            $status = bccomp($paidAmount, (string) $lockedLoan->total_amount, 18) >= 0
                ? LoanStatus::Completed
                : LoanStatus::Active;

            $lockedLoan->forceFill([
                'paid_amount' => $paidAmount,
                'status' => $status,
            ])->save();

            return $lockedLoan->fresh(['party', 'account', 'transactions']);
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
