<?php

namespace App\Support\Accounting;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Support\AccountValidationRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class AccountLedgerBalance
{
    /**
     * Signed effect of a transaction on a specific cash/bank account.
     */
    public static function effectForAccount(Transaction $transaction, int $accountId): string
    {
        $amount = AccountValidationRules::normalizeBalance((string) $transaction->amount);

        $effect = match ($transaction->type) {
            TransactionType::Income => (int) $transaction->account_id === $accountId ? $amount : '0',
            TransactionType::Expense => (int) $transaction->account_id === $accountId
                ? bcmul($amount, '-1', 18)
                : '0',
            TransactionType::Transfer => match (true) {
                (int) $transaction->account_id === $accountId => bcmul($amount, '-1', 18),
                (int) $transaction->destination_account_id === $accountId => $amount,
                default => '0',
            },
        };

        return AccountValidationRules::normalizeBalance($effect);
    }

    /**
     * Attach absolute running balance_after for one account's ledger page rows.
     *
     * @param  Enumerable<int, Transaction>|iterable<int, Transaction>  $transactions
     * @return array<int, string> map of transaction id => balance_after
     */
    public static function attachForAccount(Account $account, iterable $transactions): array
    {
        $rows = self::collectTransactions($transactions);

        if ($rows->isEmpty()) {
            return [];
        }

        $accountId = (int) $account->id;
        $chronological = $rows->sort(fn (Transaction $a, Transaction $b): int => self::compareChronological($a, $b))->values();
        $oldest = $chronological->first();

        $running = self::balanceBefore($account, $oldest);
        $balances = [];

        foreach ($chronological as $transaction) {
            $running = bcadd($running, self::effectForAccount($transaction, $accountId), 18);
            $normalized = AccountValidationRules::normalizeBalance($running);
            $balances[(int) $transaction->id] = $normalized;
            $transaction->setAttribute('balance_after', $normalized);
        }

        return $balances;
    }

    /**
     * Attach balance_after for mixed-account listing rows (source account only).
     *
     * @param  Enumerable<int, Transaction>|iterable<int, Transaction>  $transactions
     * @return array<int, string>
     */
    public static function attachForMixedAccounts(iterable $transactions): array
    {
        $rows = self::collectTransactions($transactions);

        if ($rows->isEmpty()) {
            return [];
        }

        $balances = [];

        $grouped = $rows->groupBy(fn (Transaction $transaction): int => (int) $transaction->account_id);

        $accountIds = $grouped->keys()->map(fn (mixed $id): int => (int) $id)->all();
        $accounts = Account::query()
            ->whereIn('id', $accountIds)
            ->get(['id', 'opening_balance'])
            ->keyBy('id');

        foreach ($grouped as $accountId => $group) {
            $account = $accounts->get((int) $accountId);

            if ($account === null) {
                continue;
            }

            $balances += self::attachForAccount($account, $group);
        }

        return $balances;
    }

    /**
     * @param  Enumerable<int, Transaction>|iterable<int, Transaction>  $transactions
     * @return Collection<int, Transaction>
     */
    protected static function collectTransactions(iterable $transactions): Collection
    {
        return Collection::make($transactions)->values();
    }

    protected static function compareChronological(Transaction $a, Transaction $b): int
    {
        $dateA = $a->transaction_date instanceof \DateTimeInterface
            ? $a->transaction_date->format('Y-m-d')
            : (string) $a->transaction_date;
        $dateB = $b->transaction_date instanceof \DateTimeInterface
            ? $b->transaction_date->format('Y-m-d')
            : (string) $b->transaction_date;

        $dateCompare = $dateA <=> $dateB;

        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        return (int) $a->id <=> (int) $b->id;
    }

    /**
     * Opening balance + effects of all transactions strictly before $before.
     */
    protected static function balanceBefore(Account $account, Transaction $before): string
    {
        $accountId = (int) $account->id;
        $beforeDate = $before->transaction_date instanceof \DateTimeInterface
            ? $before->transaction_date->format('Y-m-d')
            : (string) $before->transaction_date;
        $beforeId = (int) $before->id;

        $totals = Transaction::query()
            ->where(function ($query) use ($accountId): void {
                $query->where('account_id', $accountId)
                    ->orWhere('destination_account_id', $accountId);
            })
            ->where(function ($query) use ($beforeDate, $beforeId): void {
                $query->where('transaction_date', '<', $beforeDate)
                    ->orWhere(function ($query) use ($beforeDate, $beforeId): void {
                        $query->whereDate('transaction_date', $beforeDate)
                            ->where('id', '<', $beforeId);
                    });
            })
            ->toBase()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? AND account_id = ? THEN amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN type = ? AND account_id = ? THEN amount ELSE 0 END), 0) as expense_total,
                COALESCE(SUM(CASE WHEN type = ? AND account_id = ? THEN amount ELSE 0 END), 0) as transfer_out_total,
                COALESCE(SUM(CASE WHEN type = ? AND destination_account_id = ? THEN amount ELSE 0 END), 0) as transfer_in_total',
                [
                    TransactionType::Income->value,
                    $accountId,
                    TransactionType::Expense->value,
                    $accountId,
                    TransactionType::Transfer->value,
                    $accountId,
                    TransactionType::Transfer->value,
                    $accountId,
                ]
            )
            ->first();

        $balance = (string) $account->opening_balance;
        $balance = bcadd($balance, (string) ($totals->income_total ?? '0'), 18);
        $balance = bcsub($balance, (string) ($totals->expense_total ?? '0'), 18);
        $balance = bcsub($balance, (string) ($totals->transfer_out_total ?? '0'), 18);
        $balance = bcadd($balance, (string) ($totals->transfer_in_total ?? '0'), 18);

        return $balance;
    }
}
