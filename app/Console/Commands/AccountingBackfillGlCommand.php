<?php

namespace App\Console\Commands;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Actions\Ledger\BackfillBusinessGlAction;
use App\Models\Accounting\FiscalYear;
use App\Models\Business;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AccountingBackfillGlCommand extends Command
{
    protected $signature = 'accounting:backfill-gl
                            {business? : Business ID}
                            {--all : Backfill every business}
                            {--skip-seed : Skip COA seed / default sync / category migrate}
                            {--post : Create posted vouchers instead of drafts}';

    protected $description = 'Seed COA, link categories, and create draft GL vouchers for historical invoices/transactions (idempotent)';

    public function handle(
        SeedBusinessChartOfAccountsAction $seedCoa,
        SyncBusinessLedgerDefaultAccountsAction $syncDefaults,
        BackfillBusinessGlAction $backfill,
    ): int {
        $businesses = $this->option('all')
            ? Business::query()->orderBy('id')->get()
            : collect([Business::query()->find($this->argument('business'))])->filter();

        if ($businesses->isEmpty()) {
            $this->error('Provide a business ID or use --all.');

            return self::FAILURE;
        }

        foreach ($businesses as $business) {
            $this->line("=== Business #{$business->id} ({$business->name}) ===");

            if (! $this->option('skip-seed')) {
                $seedCoa->handle($business);
                $syncDefaults->handle($business, overwrite: false);
                Artisan::call('accounting:sync-gl-details', ['business' => $business->id]);
                Artisan::call('accounting:migrate-categories-to-gl', ['business' => $business->id]);
            }

            $owner = User::query()->find($business->owner_id);

            if ($owner === null) {
                $this->warn('  No owner user — skipped posting.');

                continue;
            }

            $hasFiscalYear = FiscalYear::query()
                ->where('business_id', $business->id)
                ->where('is_closed', false)
                ->exists();

            if (! $hasFiscalYear) {
                $this->warn('  No open fiscal year — skipped historical vouchers (COA/categories still synced).');

                continue;
            }

            $stats = $backfill->handle($business, $owner, forcePost: (bool) $this->option('post'));

            $this->info(sprintf(
                '  Invoices: posted=%d skipped=%d failed=%d | Transactions: posted=%d skipped=%d failed=%d',
                $stats['invoices']['posted'],
                $stats['invoices']['skipped'],
                $stats['invoices']['failed'],
                $stats['transactions']['posted'],
                $stats['transactions']['skipped'],
                $stats['transactions']['failed'],
            ));
        }

        return self::SUCCESS;
    }
}
