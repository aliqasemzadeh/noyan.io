<?php

namespace App\Actions\Ledger;

use App\Actions\Transactions\DeleteTransactionAction;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DeleteJournalEntryAction
{
    public function __construct(
        protected DeleteTransactionAction $deleteTransaction,
    ) {}

    public function handle(User $user, JournalEntry $entry): void
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to delete a journal entry.');
        }

        if ((int) $entry->business_id !== (int) $businessId) {
            throw new RuntimeException('Journal entry does not belong to the current business.');
        }

        $entry->loadMissing('referenceable');
        $reference = $entry->referenceable;

        // System vouchers that mirror a treasury transaction: delete the source transaction
        // (which voids linked journals and reverses cash/party balances).
        if ($reference instanceof Transaction) {
            $this->deleteTransaction->handle($user, $reference);

            return;
        }

        if ($entry->isSystemGenerated()) {
            throw ValidationException::withMessages([
                'entry' => [__('general.journal_system_generated_locked')],
            ]);
        }

        DB::transaction(function () use ($entry, $businessId): void {
            $locked = JournalEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->business_id !== (int) $businessId) {
                throw new RuntimeException('Journal entry does not belong to the current business.');
            }

            if ($locked->isSystemGenerated()) {
                throw ValidationException::withMessages([
                    'entry' => [__('general.journal_system_generated_locked')],
                ]);
            }

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages([
                    'entry' => [__('general.journal_only_draft_deletable')],
                ]);
            }

            $locked->lines()->each(fn ($line) => $line->delete());
            $locked->delete();
        });
    }
}
