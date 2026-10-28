<?php

namespace App\Console\Commands;

use App\Actions\Ledger\ConvertManualSettlementJournalsAction;
use App\Models\Business;
use App\Models\User;
use Illuminate\Console\Command;

class AccountingConvertManualSettlementsCommand extends Command
{
    protected $signature = 'accounting:convert-manual-settlements
                            {business? : Business ID}
                            {--all : Convert for every business}';

    protected $description = 'Convert unlinked cash↔party settlement journals into treasury transactions (idempotent for already-linked vouchers)';

    public function handle(ConvertManualSettlementJournalsAction $convert): int
    {
        $businesses = $this->option('all')
            ? Business::query()->orderBy('id')->get()
            : collect([Business::query()->find($this->argument('business'))])->filter();

        if ($businesses->isEmpty()) {
            $this->error('Provide a business ID or use --all.');

            return self::FAILURE;
        }

        foreach ($businesses as $business) {
            $this->line("=== Business #{$business->id} ({$business->name}) ===");

            $owner = User::query()->find($business->owner_id);

            if ($owner === null) {
                $this->warn('  No owner user — skipped.');

                continue;
            }

            $stats = $convert->handle($business, $owner);

            $this->info(sprintf(
                '  converted=%d skipped=%d failed=%d',
                $stats['converted'],
                $stats['skipped'],
                $stats['failed'],
            ));
        }

        return self::SUCCESS;
    }
}
