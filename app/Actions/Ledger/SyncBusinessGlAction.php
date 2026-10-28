<?php

namespace App\Actions\Ledger;

use App\Enums\Accounting\AutoPostMode;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use Throwable;

class SyncBusinessGlAction
{
    public function __construct(
        protected AutoPostingEngine $engine,
        protected BackfillBusinessGlAction $backfill,
    ) {}

    /**
     * Re-sync linked vouchers from source docs, then backfill any still missing.
     *
     * @return array{
     *     resynced: array{invoices: int, transactions: int, failed: int},
     *     backfill: array{
     *         invoices: array{posted: int, skipped: int, failed: int},
     *         transactions: array{posted: int, skipped: int, failed: int}
     *     }
     * }
     */
    public function handle(Business $business, User $user, bool $forcePost = false): array
    {
        $previousBusinessId = $user->current_business_id;
        $user->forceFill(['current_business_id' => $business->id])->saveQuietly();

        $resynced = ['invoices' => 0, 'transactions' => 0, 'failed' => 0];

        try {
            // Only rebuild existing links when forcing post or auto-post is enabled.
            // Avoid voiding vouchers when mode is Off unless an explicit force rebuild is requested.
            if ($forcePost || $this->shouldResyncLinked($business)) {
                Invoice::query()
                    ->where('business_id', $business->id)
                    ->whereNotNull('finalized_at')
                    ->whereNotNull('party_id')
                    ->orderBy('id')
                    ->each(function (Invoice $invoice) use ($user, $forcePost, &$resynced): void {
                        if (! $this->hasLinkedVoucher($invoice)) {
                            return;
                        }

                        try {
                            $this->engine->syncInvoice($invoice, $user, forcePost: $forcePost);
                            $resynced['invoices']++;
                        } catch (Throwable) {
                            $resynced['failed']++;
                        }
                    });

                Transaction::query()
                    ->where('business_id', $business->id)
                    ->with(['category', 'account', 'destinationAccount', 'party'])
                    ->orderBy('id')
                    ->each(function (Transaction $transaction) use ($user, $forcePost, &$resynced): void {
                        if (! $this->hasLinkedVoucher($transaction)) {
                            return;
                        }

                        try {
                            $this->engine->syncTransaction($transaction, $user, forcePost: $forcePost);
                            $resynced['transactions']++;
                        } catch (Throwable) {
                            $resynced['failed']++;
                        }
                    });
            }

            $backfill = $this->backfill->handle($business, $user, forcePost: $forcePost);
        } finally {
            $user->forceFill(['current_business_id' => $previousBusinessId])->saveQuietly();
        }

        return [
            'resynced' => $resynced,
            'backfill' => $backfill,
        ];
    }

    protected function shouldResyncLinked(Business $business): bool
    {
        $mode = BusinessLedgerSetting::forBusiness($business)->auto_post_mode;

        return $mode !== null && $mode !== AutoPostMode::Off;
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
