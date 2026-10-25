<?php

namespace App\Console\Commands;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Models\Business;
use Illuminate\Console\Command;

class AccountingSeedCoaCommand extends Command
{
    protected $signature = 'accounting:seed-coa
                            {business? : Business ID}
                            {--all : Seed chart of accounts for every business}
                            {--force : Upsert system accounts even if a chart already exists}';

    protected $description = 'Seed the standard chart of accounts for one or all businesses';

    public function handle(
        SeedBusinessChartOfAccountsAction $action,
        SyncBusinessLedgerDefaultAccountsAction $syncDefaults,
    ): int {
        if ($this->option('all')) {
            $businesses = Business::query()->orderBy('id')->get();

            if ($businesses->isEmpty()) {
                $this->warn('No businesses found.');

                return self::SUCCESS;
            }

            foreach ($businesses as $business) {
                $accounts = $action->handle($business, (bool) $this->option('force'));
                $syncDefaults->handle($business, overwrite: (bool) $this->option('force'));
                $this->info("Business #{$business->id} ({$business->name}): ".count($accounts).' accounts.');
            }

            return self::SUCCESS;
        }

        $businessId = $this->argument('business');

        if ($businessId === null) {
            $this->error('Provide a business ID or use --all.');

            return self::FAILURE;
        }

        $business = Business::query()->find($businessId);

        if ($business === null) {
            $this->error("Business [{$businessId}] was not found.");

            return self::FAILURE;
        }

        $accounts = $action->handle($business, (bool) $this->option('force'));
        $syncDefaults->handle($business, overwrite: (bool) $this->option('force'));
        $this->info('Seeded '.count($accounts)." GL accounts for business #{$business->id}.");

        return self::SUCCESS;
    }
}
