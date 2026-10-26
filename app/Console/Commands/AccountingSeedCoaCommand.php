<?php

namespace App\Console\Commands;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Enums\Accounting\ChartTemplate;
use App\Models\Business;
use Illuminate\Console\Command;

class AccountingSeedCoaCommand extends Command
{
    protected $signature = 'accounting:seed-coa
                            {business? : Business ID}
                            {--all : Seed chart of accounts for every business}
                            {--force : Also overwrite existing default-account mappings (COA upsert is always idempotent)}
                            {--overwrite-defaults : Alias of --force for default account mappings}';

    protected $description = 'Upsert the standard chart of accounts (no duplicates) and fill empty ledger default mappings';

    public function handle(
        SeedBusinessChartOfAccountsAction $action,
        SyncBusinessLedgerDefaultAccountsAction $syncDefaults,
    ): int {
        $overwriteDefaults = (bool) $this->option('force') || (bool) $this->option('overwrite-defaults');

        if ($this->option('all')) {
            $businesses = Business::query()->orderBy('id')->get();

            if ($businesses->isEmpty()) {
                $this->warn('No businesses found.');

                return self::SUCCESS;
            }

            foreach ($businesses as $business) {
                $accounts = $action->handle($business, template: $business->category?->chartTemplate() ?? ChartTemplate::Trading);
                $syncDefaults->handle($business, overwrite: $overwriteDefaults);
                $this->info("Business #{$business->id} ({$business->name}): ".count($accounts).' system accounts upserted.');
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

        $accounts = $action->handle($business, template: $business->category?->chartTemplate() ?? ChartTemplate::Trading);
        $syncDefaults->handle($business, overwrite: $overwriteDefaults);
        $this->info('Upserted '.count($accounts)." system GL accounts for business #{$business->id}.");

        return self::SUCCESS;
    }
}
