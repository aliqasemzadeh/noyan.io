<?php

namespace App\Actions\Ledger;

use App\Actions\Ledger\Concerns\ValidatesJournalLines;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class UpdateJournalEntryAction
{
    use ValidatesJournalLines;

    /**
     * @param  array{
     *     fiscal_year_id: int,
     *     entry_date: string,
     *     description: string
     * }  $entryData
     * @param  list<array<string, mixed>>  $linesData
     */
    public function handle(User $user, JournalEntry $entry, array $entryData, array $linesData): JournalEntry
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to update a journal entry.');
        }

        return DB::transaction(function () use ($entry, $entryData, $linesData, $businessId): JournalEntry {
            $locked = JournalEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->business_id !== (int) $businessId) {
                throw new RuntimeException('Journal entry does not belong to the current business.');
            }

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'entry' => [__('general.journal_only_draft_editable')],
                ]);
            }

            $entryDate = (string) $entryData['entry_date'];
            $description = trim((string) ($entryData['description'] ?? ''));

            if ($description === '') {
                throw ValidationException::withMessages([
                    'description' => [__('general.journal_description_required')],
                ]);
            }

            $fiscalYear = $this->resolveOpenFiscalYear(
                (int) $businessId,
                (int) $entryData['fiscal_year_id'],
                $entryDate,
            );

            $lines = $this->normalizeAndValidateLines((int) $businessId, $linesData);

            $locked->update([
                'fiscal_year_id' => $fiscalYear->id,
                'entry_date' => $entryDate,
                'description' => $description,
            ]);

            $locked->lines()->each(function (JournalLine $line): void {
                $line->delete();
            });

            foreach ($lines as $line) {
                $locked->lines()->create($line);
            }

            return $locked->fresh(['lines', 'fiscalYear']);
        });
    }
}
