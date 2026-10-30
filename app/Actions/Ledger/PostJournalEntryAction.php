<?php

namespace App\Actions\Ledger;

use App\Actions\Ledger\Concerns\ValidatesJournalLines;
use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PostJournalEntryAction
{
    use ValidatesJournalLines;

    public function handle(User $user, JournalEntry $entry): JournalEntry
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to post a journal entry.');
        }

        return DB::transaction(function () use ($user, $entry, $businessId): JournalEntry {
            $locked = JournalEntry::query()
                ->with('lines')
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->business_id !== (int) $businessId) {
                throw new RuntimeException('Journal entry does not belong to the current business.');
            }

            if (! $locked->isOpen()) {
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

            $posted = $locked->fresh(['lines', 'fiscalYear']);

            try {
                app(SyncTreasuryFromPostedJournalAction::class)->handle($user, $posted);
            } catch (Throwable $exception) {
                Log::error('Treasury sync from posted journal failed.', [
                    'journal_entry_id' => $posted->id,
                    'message' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            return $posted->fresh(['lines', 'fiscalYear', 'referenceable']);
        });
    }
}
