<?php

namespace App\Actions\Accounts;

use App\Models\Accounting\Account;
use App\Support\AccountValidationRules;
use Illuminate\Support\Facades\DB;

class RebalanceAccountAction
{
    public function handle(Account $account): Account
    {
        return DB::transaction(function () use ($account): Account {
            /** @var Account $locked */
            $locked = Account::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $computed = AccountValidationRules::normalizeBalance(
                $locked->balanceFromTransactions()
            );

            $locked->forceFill([
                'current_balance' => $computed,
            ])->save();

            Account::forgetBalanceCache(
                (int) $locked->business_id,
                (int) $locked->id
            );

            return $locked->refresh();
        });
    }
}
