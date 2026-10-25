<?php

namespace App\Console\Commands;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use App\Support\Accounting\CategoryGlAccountMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class AccountingBackfillGlCommand extends Command
{
    protected $signature = 'accounting:backfill-gl
                            {business? : Business ID}
                            {--all : Backfill every business}
                            {--skip-seed : Skip COA seed / default sync / category migrate}';

    protected $description = 'Seed COA, link categories, and create draft GL vouchers for historical invoices/transactions (idempotent)';

    public function handle(
        SeedBusinessChartOfAccountsAction $seedCoa,
        SyncBusinessLedgerDefaultAccountsAction $syncDefaults,
        AutoPostingEngine $engine,
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

            $previousBusinessId = $owner->current_business_id;
            $owner->forceFill(['current_business_id' => $business->id])->saveQuietly();

            try {
                $invoiceStats = $this->backfillInvoices($business, $owner, $engine);
                $txnStats = $this->backfillTransactions($business, $owner, $engine);

                $this->info(sprintf(
                    '  Invoices: posted=%d skipped=%d failed=%d | Transactions: posted=%d skipped=%d failed=%d',
                    $invoiceStats['posted'],
                    $invoiceStats['skipped'],
                    $invoiceStats['failed'],
                    $txnStats['posted'],
                    $txnStats['skipped'],
                    $txnStats['failed'],
                ));
            } finally {
                $owner->forceFill(['current_business_id' => $previousBusinessId])->saveQuietly();
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{posted: int, skipped: int, failed: int}
     */
    protected function backfillInvoices(Business $business, User $user, AutoPostingEngine $engine): array
    {
        $stats = ['posted' => 0, 'skipped' => 0, 'failed' => 0];

        Invoice::query()
            ->where('business_id', $business->id)
            ->whereNotNull('finalized_at')
            ->whereNotNull('party_id')
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($user, $engine, &$stats): void {
                if ($this->hasLinkedVoucher($invoice)) {
                    $stats['skipped']++;

                    return;
                }

                try {
                    $entry = $engine->postInvoice($invoice, $user, forceDraft: true);
                    $entry === null ? $stats['skipped']++ : $stats['posted']++;
                } catch (Throwable $exception) {
                    $stats['failed']++;
                    $this->warn("  Invoice #{$invoice->id}: {$exception->getMessage()}");
                }
            });

        return $stats;
    }

    /**
     * @return array{posted: int, skipped: int, failed: int}
     */
    protected function backfillTransactions(Business $business, User $user, AutoPostingEngine $engine): array
    {
        $stats = ['posted' => 0, 'skipped' => 0, 'failed' => 0];

        Transaction::query()
            ->where('business_id', $business->id)
            ->with(['category', 'account', 'destinationAccount', 'party'])
            ->orderBy('id')
            ->each(function (Transaction $transaction) use ($business, $user, $engine, &$stats): void {
                if ($this->hasLinkedVoucher($transaction)) {
                    $stats['skipped']++;

                    return;
                }

                if (
                    in_array($transaction->type, [TransactionType::Income, TransactionType::Expense], true)
                    && $transaction->category_id === null
                    && $transaction->invoice_id === null
                ) {
                    $leaf = CategoryGlAccountMap::defaultLeafCategory(
                        (int) $business->id,
                        $transaction->type,
                    );

                    if ($leaf !== null) {
                        $transaction->forceFill(['category_id' => $leaf->id])->saveQuietly();
                        $transaction->load('category');
                    }
                }

                try {
                    $entry = $engine->postTransaction($transaction, $user, forceDraft: true);
                    $entry === null ? $stats['skipped']++ : $stats['posted']++;
                } catch (Throwable $exception) {
                    $stats['failed']++;
                    $this->warn("  Transaction #{$transaction->id}: {$exception->getMessage()}");
                }
            });

        return $stats;
    }

    protected function hasLinkedVoucher(Invoice|Transaction $model): bool
    {
        return JournalEntry::query()
            ->where('business_id', $model->business_id)
            ->where('referenceable_type', $model->getMorphClass())
            ->where('referenceable_id', $model->id)
            ->where('voucher_number', 'not like', '%__del_%')
            ->exists();
    }
}
