<?php

namespace App\Console\Commands;

use App\Actions\Accounting\RebuildAccountingBalancesAction;
use App\Jobs\Accounting\RebuildAccountingBalancesJob;
use App\Models\Business;
use Illuminate\Console\Command;

class AccountingRebuildBalancesCommand extends Command
{
    protected $signature = 'accounting:rebuild-balances
                            {business? : Business ID}
                            {--all : Rebuild every business}
                            {--queue : Dispatch the rebuild to the queue}';

    protected $description = 'Rebuild transaction balance_after columns and account current_balance from ledger history';

    public function handle(RebuildAccountingBalancesAction $action): int
    {
        $businessId = $this->argument('business');

        if ($businessId !== null) {
            $businesses = collect([Business::query()->find($businessId)])->filter();
        } else {
            $businesses = Business::query()->orderBy('id')->get();
        }

        if ($businesses->isEmpty()) {
            $this->error($businessId !== null
                ? 'Business not found.'
                : 'No businesses found.');

            return self::FAILURE;
        }

        if ($this->option('queue')) {
            $businessIds = $businessId !== null
                ? [(int) $businessId]
                : null;

            RebuildAccountingBalancesJob::dispatch($businessIds);

            $this->info('Rebuild queued for '.$businesses->count().' business(es).');

            return self::SUCCESS;
        }

        $this->info('Rebuilding accounting balances for '.$businesses->count().' business(es)...');

        $stats = $action->handle($businesses);

        $this->info(sprintf(
            'Done. businesses=%d accounts=%d',
            $stats['businesses'],
            $stats['accounts'],
        ));

        return self::SUCCESS;
    }
}
