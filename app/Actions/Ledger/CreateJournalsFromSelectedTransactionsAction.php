<?php

namespace App\Actions\Ledger;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use App\Support\Accounting\CategoryGlAccountMap;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CreateJournalsFromSelectedTransactionsAction
{
    public function __construct(
        protected AutoPostingEngine $autoPostingEngine,
        protected MergeTransactionsIntoTransferAction $mergeIntoTransfer,
    ) {}

    /**
     * @param  list<int>  $transactionIds
     * @return array{
     *     mode: 'transfer'|'independent',
     *     created: int,
     *     skipped: int,
     *     failed: int,
     *     transfer: array{
     *         from_account: string,
     *         to_account: string,
     *         amount: string,
     *         currency: string,
     *         voucher_number: string,
     *         transfer_id: int
     *     }|null,
     *     errors: list<string>
     * }
     */
    public function handle(User $user, array $transactionIds): array
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to create journals.');
        }

        $transactions = $this->loadTransactions($businessId, $transactionIds);

        if ($transactions->isEmpty()) {
            throw ValidationException::withMessages([
                'transactions' => [__('general.bulk_nothing_selected')],
            ]);
        }

        $pair = $this->detectTransferPair($transactions);

        if ($pair !== null) {
            return $this->handleTransferPair($user, $pair['expense'], $pair['income']);
        }

        return $this->handleIndependent($user, $transactions);
    }

    /**
     * @param  list<int>  $transactionIds
     * @return array{
     *     mode: 'transfer'|'independent',
     *     transfer: array{
     *         from_account: string,
     *         to_account: string,
     *         amount: string,
     *         currency: string,
     *         expense_id: int,
     *         income_id: int
     *     }|null,
     *     items: list<array{
     *         id: int,
     *         type: string,
     *         amount: string,
     *         currency: string,
     *         account: string,
     *         note: string|null,
     *         status: 'will_create'|'already_linked',
     *         voucher_number: string|null
     *     }>
     * }
     */
    public function preview(User $user, array $transactionIds): array
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to preview journals.');
        }

        $transactions = $this->loadTransactions($businessId, $transactionIds);
        $pair = $this->detectTransferPair($transactions);

        if ($pair !== null) {
            $expense = $pair['expense'];
            $income = $pair['income'];

            return [
                'mode' => 'transfer',
                'transfer' => [
                    'from_account' => (string) ($expense->account?->name ?? '#'.$expense->account_id),
                    'to_account' => (string) ($income->account?->name ?? '#'.$income->account_id),
                    'amount' => (string) $expense->amount,
                    'currency' => (string) $expense->currency,
                    'expense_id' => (int) $expense->id,
                    'income_id' => (int) $income->id,
                ],
                'items' => [],
            ];
        }

        $items = [];

        foreach ($transactions as $transaction) {
            $linked = $this->activeLinkedJournal($transaction);

            $items[] = [
                'id' => (int) $transaction->id,
                'type' => $transaction->type->label(),
                'amount' => (string) $transaction->amount,
                'currency' => (string) $transaction->currency,
                'account' => (string) ($transaction->account?->name ?? '#'.$transaction->account_id),
                'note' => $transaction->note,
                'status' => $linked !== null ? 'already_linked' : 'will_create',
                'voucher_number' => $linked?->voucher_number,
            ];
        }

        return [
            'mode' => 'independent',
            'transfer' => null,
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array{expense: Transaction, income: Transaction}|null
     */
    public function detectTransferPair(Collection $transactions): ?array
    {
        if ($transactions->count() !== 2) {
            return null;
        }

        $expense = $transactions->first(
            fn (Transaction $transaction): bool => $transaction->type === TransactionType::Expense
        );
        $income = $transactions->first(
            fn (Transaction $transaction): bool => $transaction->type === TransactionType::Income
        );

        if ($expense === null || $income === null) {
            return null;
        }

        try {
            $this->mergeIntoTransfer->assertCompatiblePair($expense, $income);
        } catch (ValidationException) {
            return null;
        }

        if ($this->activeLinkedJournal($expense) !== null || $this->activeLinkedJournal($income) !== null) {
            return null;
        }

        return [
            'expense' => $expense,
            'income' => $income,
        ];
    }

    /**
     * @return array{
     *     mode: 'transfer',
     *     created: int,
     *     skipped: int,
     *     failed: int,
     *     transfer: array{
     *         from_account: string,
     *         to_account: string,
     *         amount: string,
     *         currency: string,
     *         voucher_number: string,
     *         transfer_id: int
     *     },
     *     errors: list<string>
     * }
     */
    protected function handleTransferPair(User $user, Transaction $expense, Transaction $income): array
    {
        $result = $this->mergeIntoTransfer->handle($user, $expense, $income);

        return [
            'mode' => 'transfer',
            'created' => 1,
            'skipped' => 0,
            'failed' => 0,
            'transfer' => [
                'from_account' => (string) ($result['transfer']->account?->name ?? '#'.$result['transfer']->account_id),
                'to_account' => (string) ($result['transfer']->destinationAccount?->name ?? '#'.$result['transfer']->destination_account_id),
                'amount' => (string) $result['transfer']->amount,
                'currency' => (string) $result['transfer']->currency,
                'voucher_number' => (string) $result['journal']->voucher_number,
                'transfer_id' => (int) $result['transfer']->id,
            ],
            'errors' => [],
        ];
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array{
     *     mode: 'independent',
     *     created: int,
     *     skipped: int,
     *     failed: int,
     *     transfer: null,
     *     errors: list<string>
     * }
     */
    protected function handleIndependent(User $user, Collection $transactions): array
    {
        $created = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($transactions as $transaction) {
            if ($this->activeLinkedJournal($transaction) !== null) {
                $skipped++;

                continue;
            }

            try {
                $this->ensureDefaultCategory($transaction);

                $entry = $this->autoPostingEngine->postTransaction(
                    $transaction->fresh(['account', 'destinationAccount', 'party', 'category', 'invoice']) ?? $transaction,
                    $user,
                    forcePost: true,
                );

                if ($entry === null) {
                    $failed++;
                    $errors[] = __('general.bulk_create_journal_item_failed', [
                        'id' => $transaction->id,
                        'message' => __('general.bulk_create_journal_failed'),
                    ]);

                    continue;
                }

                $created++;
            } catch (Throwable $exception) {
                $failed++;
                $errors[] = __('general.bulk_create_journal_item_failed', [
                    'id' => $transaction->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'mode' => 'independent',
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'transfer' => null,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<int>  $transactionIds
     * @return Collection<int, Transaction>
     */
    protected function loadTransactions(int $businessId, array $transactionIds): Collection
    {
        $ids = collect($transactionIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Transaction::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $ids)
            ->with(['account', 'destinationAccount', 'party', 'category', 'invoice', 'journalEntries'])
            ->orderBy('id')
            ->get();
    }

    protected function activeLinkedJournal(Transaction $transaction): ?JournalEntry
    {
        return $transaction->journalEntries
            ->first(function (JournalEntry $entry): bool {
                return ! str_contains((string) $entry->voucher_number, '__del_');
            });
    }

    protected function ensureDefaultCategory(Transaction $transaction): void
    {
        if (
            ! in_array($transaction->type, [TransactionType::Income, TransactionType::Expense], true)
            || $transaction->category_id !== null
            || $transaction->invoice_id !== null
        ) {
            return;
        }

        $leaf = CategoryGlAccountMap::defaultLeafCategory(
            (int) $transaction->business_id,
            $transaction->type,
        );

        if ($leaf === null) {
            return;
        }

        $transaction->forceFill(['category_id' => $leaf->id])->saveQuietly();
        $transaction->setRelation('category', $leaf);
    }
}
