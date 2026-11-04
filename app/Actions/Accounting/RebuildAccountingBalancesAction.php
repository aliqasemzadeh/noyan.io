<?php

namespace App\Actions\Accounting;

use App\Models\Accounting\Account;
use App\Models\Business;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RebuildAccountingBalancesAction
{
    public function __construct(
        protected RebuildAccountLedgerBalancesAction $rebuildAccount,
    ) {}

    /**
     * @param  Collection<int, Business>|iterable<int, Business>|null  $businesses
     * @return array{accounts: int, businesses: int}
     */
    public function handle(?iterable $businesses = null): array
    {
        $businessCollection = $businesses === null
            ? Business::query()->orderBy('id')->get()
            : Collection::make($businesses)->values();

        $accountsRebuilt = 0;

        foreach ($businessCollection as $business) {
            $accounts = Account::query()
                ->where('business_id', $business->id)
                ->orderBy('id')
                ->get();

            foreach ($accounts as $account) {
                $this->rebuildAccount->handle($account);
                $accountsRebuilt++;
            }

            Log::info('Rebuilt accounting balances for business.', [
                'business_id' => $business->id,
                'accounts' => $accounts->count(),
            ]);
        }

        return [
            'accounts' => $accountsRebuilt,
            'businesses' => $businessCollection->count(),
        ];
    }
}
