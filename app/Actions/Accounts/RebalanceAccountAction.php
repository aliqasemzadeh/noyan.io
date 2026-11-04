<?php

namespace App\Actions\Accounts;

use App\Actions\Accounting\RebuildAccountLedgerBalancesAction;
use App\Models\Accounting\Account;

class RebalanceAccountAction
{
    public function __construct(
        protected RebuildAccountLedgerBalancesAction $rebuildAccountLedgerBalances,
    ) {}

    public function handle(Account $account): Account
    {
        return $this->rebuildAccountLedgerBalances->handle($account);
    }
}
