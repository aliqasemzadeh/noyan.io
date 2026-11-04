<?php

namespace App\Actions\Accounting;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Support\AccountValidationRules;
use App\Support\Accounting\AccountLedgerBalance;
use Illuminate\Support\Facades\DB;

class RebuildAccountLedgerBalancesAction
{
    public function handle(Account $account): Account
    {
        return DB::transaction(function () use ($account): Account {
            /** @var Account $locked */
            $locked = Account::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $accountId = (int) $locked->id;

            $transactions = Transaction::query()
                ->where(function ($query) use ($accountId): void {
                    $query->where('account_id', $accountId)
                        ->orWhere('destination_account_id', $accountId);
                })
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $running = AccountValidationRules::normalizeBalance((string) $locked->opening_balance);

            foreach ($transactions as $transaction) {
                $running = AccountValidationRules::normalizeBalance(
                    bcadd($running, AccountLedgerBalance::effectForAccount($transaction, $accountId), 18)
                );

                if ((int) $transaction->account_id === $accountId) {
                    $payload = [
                        'balance_after' => $running,
                    ];

                    if ($transaction->type !== TransactionType::Transfer) {
                        $payload['destination_balance_after'] = null;
                    }

                    $transaction->forceFill($payload)->saveQuietly();

                    continue;
                }

                $transaction->forceFill([
                    'destination_balance_after' => $running,
                ])->saveQuietly();
            }

            $locked->forceFill([
                'current_balance' => $running,
            ])->save();

            Account::forgetBalanceCache(
                (int) $locked->business_id,
                (int) $locked->id
            );

            return $locked->refresh();
        });
    }
}
