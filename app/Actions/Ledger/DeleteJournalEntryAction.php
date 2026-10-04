<?php

namespace App\Actions\Ledger;

use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DeleteJournalEntryAction
{
    public function handle(User $user, JournalEntry $entry): void
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to delete a journal entry.');
        }

        DB::transaction(function () use ($entry, $businessId): void {
            $locked = JournalEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->business_id !== (int) $businessId) {
                throw new RuntimeException('Journal entry does not belong to the current business.');
            }

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'entry' => [__('general.journal_only_draft_deletable')],
                ]);
            }

            $locked->delete();
        });
    }
}
