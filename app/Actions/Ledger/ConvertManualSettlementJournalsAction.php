<?php

namespace App\Actions\Ledger;

use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\JournalEntry;
use App\Models\Business;
use App\Models\User;
use Throwable;

class ConvertManualSettlementJournalsAction
{
    public function __construct(
        protected SyncTreasuryFromPostedJournalAction $syncTreasuryFromPostedJournal,
    ) {}

    /**
     * Convert unlinked cash↔party settlements and cash↔cash transfers into treasury transactions.
     * Preserves vouchers (links referenceable; soft-creates transactions only).
     *
     * @return array{converted: int, skipped: int, failed: int}
     */
    public function handle(Business $business, User $user): array
    {
        $stats = ['converted' => 0, 'skipped' => 0, 'failed' => 0];

        $previousBusinessId = $user->current_business_id;
        $user->forceFill(['current_business_id' => $business->id])->saveQuietly();

        try {
            JournalEntry::query()
                ->where('business_id', $business->id)
                ->whereNull('referenceable_type')
                ->whereNull('referenceable_id')
                ->where('status', JournalEntryStatus::Posted)
                ->where('voucher_number', 'not like', '%__del_%')
                ->with(['lines.glAccount'])
                ->orderBy('id')
                ->each(function (JournalEntry $entry) use ($user, &$stats): void {
                    try {
                        $transaction = $this->syncTreasuryFromPostedJournal->handle(
                            $user,
                            $entry->fresh(['lines.glAccount']) ?? $entry,
                        );

                        if ($transaction === null) {
                            $stats['skipped']++;

                            return;
                        }

                        $stats['converted']++;
                    } catch (Throwable) {
                        $stats['failed']++;
                    }
                });
        } finally {
            $user->forceFill(['current_business_id' => $previousBusinessId])->saveQuietly();
        }

        return $stats;
    }
}
