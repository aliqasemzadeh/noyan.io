<?php

namespace App\Actions\Ledger;

use App\Actions\Ledger\Concerns\ValidatesJournalLines;
use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use App\Services\VoucherNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreateJournalEntryAction
{
    use ValidatesJournalLines;

    public function __construct(
        private VoucherNumberGenerator $voucherNumberGenerator,
    ) {}

    /**
     * @param  array{
     *     fiscal_year_id: int,
     *     entry_date: string,
     *     description: string,
     *     status?: string|JournalEntryStatus,
     *     referenceable_type?: string|null,
     *     referenceable_id?: int|null
     * }  $entryData
     * @param  list<array<string, mixed>>  $linesData
     */
    public function handle(User $user, array $entryData, array $linesData): JournalEntry
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to create a journal entry.');
        }

        return DB::transaction(function () use ($user, $entryData, $linesData, $businessId): JournalEntry {
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

            $status = $entryData['status'] ?? JournalEntryStatus::Draft;
            $status = $status instanceof JournalEntryStatus
                ? $status
                : JournalEntryStatus::from((string) $status);

            $entry = JournalEntry::query()->create([
                'business_id' => $businessId,
                'fiscal_year_id' => $fiscalYear->id,
                'voucher_number' => $this->voucherNumberGenerator->generate((int) $businessId, $fiscalYear->id),
                'entry_date' => $entryDate,
                'description' => $description,
                'referenceable_type' => $entryData['referenceable_type'] ?? null,
                'referenceable_id' => $entryData['referenceable_id'] ?? null,
                'status' => $status,
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            return $entry->fresh(['lines', 'fiscalYear']);
        });
    }
}
