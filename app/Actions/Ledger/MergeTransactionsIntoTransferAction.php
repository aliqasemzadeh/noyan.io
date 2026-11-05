<?php

namespace App\Actions\Ledger;

use App\Actions\Transactions\DeleteTransactionAction;
use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MergeTransactionsIntoTransferAction
{
    public function __construct(
        protected DeleteTransactionAction $deleteTransaction,
        protected ProcessTransactionAction $processTransaction,
        protected AutoPostingEngine $autoPostingEngine,
    ) {}

    /**
     * Merge one expense + one income (same amount, different accounts) into a single transfer + journal.
     *
     * @return array{
     *     transfer: Transaction,
     *     journal: JournalEntry,
     *     merged_from: list<int>
     * }
     */
    public function handle(User $user, Transaction $expense, Transaction $income): array
    {
        $this->assertCompatiblePair($expense, $income);

        $businessId = $user->current_business_id;

        if ($businessId === null || (int) $expense->business_id !== (int) $businessId) {
            throw new RuntimeException('Current business is required to merge transactions.');
        }

        return DB::transaction(function () use ($user, $expense, $income): array {
            $mergedFrom = [(int) $expense->id, (int) $income->id];
            $amount = (string) $expense->amount;
            $currency = (string) $expense->currency;
            $exchangeRate = $expense->exchange_rate !== null ? (string) $expense->exchange_rate : '1';
            $date = $expense->transaction_date?->format('Y-m-d H:i:s')
                ?? $income->transaction_date?->format('Y-m-d H:i:s')
                ?? now()->toDateTimeString();

            $noteParts = array_values(array_filter([
                $expense->note,
                $income->note,
                __('general.journal_merged_from_transactions', [
                    'ids' => implode(', ', $mergedFrom),
                ]),
            ]));

            $this->deleteTransaction->handle($user, $expense);
            $this->deleteTransaction->handle($user, $income);

            $transfer = $this->processTransaction->handle($user, [
                'type' => TransactionType::Transfer,
                'account_id' => (int) $expense->account_id,
                'destination_account_id' => (int) $income->account_id,
                'transaction_date' => $date,
                'amount' => $amount,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'reference_number' => $expense->reference_number ?: $income->reference_number,
                'note' => implode(' | ', $noteParts),
                'meta' => [
                    'merged_from_transaction_ids' => $mergedFrom,
                ],
                'skip_auto_post' => true,
            ]);

            $journal = $this->autoPostingEngine->postTransaction(
                $transfer,
                $user,
                forcePost: true,
            );

            if ($journal === null) {
                throw ValidationException::withMessages([
                    'transactions' => [__('general.bulk_create_journal_failed')],
                ]);
            }

            return [
                'transfer' => $transfer->fresh(['account', 'destinationAccount']) ?? $transfer,
                'journal' => $journal,
                'merged_from' => $mergedFrom,
            ];
        });
    }

    public function assertCompatiblePair(Transaction $expense, Transaction $income): void
    {
        if ($expense->type !== TransactionType::Expense || $income->type !== TransactionType::Income) {
            throw ValidationException::withMessages([
                'transactions' => [__('general.bulk_create_journal_transfer_pair_invalid')],
            ]);
        }

        if ((int) $expense->business_id !== (int) $income->business_id) {
            throw ValidationException::withMessages([
                'transactions' => [__('general.bulk_create_journal_transfer_pair_invalid')],
            ]);
        }

        if (bccomp((string) $expense->amount, (string) $income->amount, 18) !== 0) {
            throw ValidationException::withMessages([
                'transactions' => [__('general.bulk_create_journal_transfer_pair_invalid')],
            ]);
        }

        if ((string) $expense->currency !== (string) $income->currency) {
            throw ValidationException::withMessages([
                'transactions' => [__('general.bulk_create_journal_transfer_pair_invalid')],
            ]);
        }

        if ((int) $expense->account_id === (int) $income->account_id) {
            throw ValidationException::withMessages([
                'transactions' => [__('general.bulk_create_journal_transfer_pair_invalid')],
            ]);
        }

        if ($expense->destination_account_id !== null || $income->destination_account_id !== null) {
            throw ValidationException::withMessages([
                'transactions' => [__('general.bulk_create_journal_transfer_pair_invalid')],
            ]);
        }
    }
}
