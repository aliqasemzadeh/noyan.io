<?php

namespace App\Actions\Ledger;

use App\Actions\Ledger\Concerns\ValidatesJournalLines;
use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PostJournalEntryAction
{
    use ValidatesJournalLines;

    public function handle(User $user, JournalEntry $entry): JournalEntry
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to post a journal entry.');
        }

        return DB::transaction(function () use ($entry, $businessId): JournalEntry {
            $locked = JournalEntry::query()
                ->with('lines')
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->business_id !== (int) $businessId) {
                throw new RuntimeException('Journal entry does not belong to the current business.');
            }

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'entry' => [__('general.journal_only_draft_postable')],
                ]);
            }

            $this->resolveOpenFiscalYear(
                (int) $businessId,
                (int) $locked->fiscal_year_id,
                $locked->entry_date->toDateString(),
            );

            $lines = $locked->lines->map(fn ($line): array => [
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
            ])->all();

            if (count($lines) < 2) {
                throw ValidationException::withMessages([
                    'lines' => [__('general.journal_lines_minimum')],
                ]);
            }

            $this->assertBalanced($lines);

            $locked->update([
                'status' => JournalEntryStatus::Posted,
            ]);

            return $locked->fresh(['lines', 'fiscalYear']);
        });
    }
}
