<?php

namespace App\Actions\Ledger;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\Business;
use App\Models\User;
use App\Services\Accounting\AutoPostingEngine;
use App\Support\Accounting\CategoryGlAccountMap;
use Throwable;

class BackfillBusinessGlAction
{
    public function __construct(
        protected AutoPostingEngine $engine,
    ) {}

    /**
     * Create missing journal vouchers for historical invoices and transactions.
     *
     * @return array{
     *     invoices: array{posted: int, skipped: int, failed: int},
     *     transactions: array{posted: int, skipped: int, failed: int}
     * }
     */
    public function handle(Business $business, User $user, bool $forcePost = false): array
    {
        $previousBusinessId = $user->current_business_id;
        $user->forceFill(['current_business_id' => $business->id])->saveQuietly();

        try {
            return [
                'invoices' => $this->backfillInvoices($business, $user, $forcePost),
                'transactions' => $this->backfillTransactions($business, $user, $forcePost),
            ];
        } finally {
            $user->forceFill(['current_business_id' => $previousBusinessId])->saveQuietly();
        }
    }

    /**
     * @return array{posted: int, skipped: int, failed: int}
     */
    protected function backfillInvoices(Business $business, User $user, bool $forcePost): array
    {
        $stats = ['posted' => 0, 'skipped' => 0, 'failed' => 0];

        Invoice::query()
            ->where('business_id', $business->id)
            ->whereNotNull('finalized_at')
            ->whereNotNull('party_id')
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($user, $forcePost, &$stats): void {
                if ($this->hasLinkedVoucher($invoice)) {
                    $stats['skipped']++;

                    return;
                }

                try {
                    $entry = $this->engine->postInvoice(
                        $invoice,
                        $user,
                        forceDraft: ! $forcePost,
                        forcePost: $forcePost,
                    );
                    $entry === null ? $stats['skipped']++ : $stats['posted']++;
                } catch (Throwable) {
                    $stats['failed']++;
                }
            });

        return $stats;
    }

    /**
     * @return array{posted: int, skipped: int, failed: int}
     */
    protected function backfillTransactions(Business $business, User $user, bool $forcePost): array
    {
        $stats = ['posted' => 0, 'skipped' => 0, 'failed' => 0];

        Transaction::query()
            ->where('business_id', $business->id)
            ->with(['category', 'account', 'destinationAccount', 'party'])
            ->orderBy('id')
            ->each(function (Transaction $transaction) use ($business, $user, $forcePost, &$stats): void {
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
                    $entry = $this->engine->postTransaction(
                        $transaction,
                        $user,
                        forceDraft: ! $forcePost,
                        forcePost: $forcePost,
                    );
                    $entry === null ? $stats['skipped']++ : $stats['posted']++;
                } catch (Throwable) {
                    $stats['failed']++;
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
