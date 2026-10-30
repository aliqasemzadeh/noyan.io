<?php

namespace App\Actions\Ledger;

use App\Actions\Transactions\DeleteTransactionAction;
use App\Enums\Business\BusinessRole;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\BusinessUser;
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
        $isOwner = $this->userOwnsBusiness($user, (int) $businessId);

        // System vouchers that mirror a treasury transaction: delete the source transaction
        // (which voids linked journals and reverses cash/party balances).
        if ($reference instanceof Transaction) {
            $this->deleteLinkedTreasury($user, $entry, $reference);

            return;
        }

        if ($entry->isSystemGenerated()) {
            throw ValidationException::withMessages([
                'entry' => [__('general.journal_system_generated_locked')],
            ]);
        }

        DB::transaction(function () use ($entry, $businessId, $isOwner): void {
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

            if (! $locked->isOpen() && ! $isOwner) {
                throw ValidationException::withMessages([
                    'entry' => [__('general.journal_only_draft_deletable')],
                ]);
            }

            $locked->lines()->each(fn ($line) => $line->delete());
            $locked->delete();
        });
    }

    protected function deleteLinkedTreasury(User $user, JournalEntry $entry, Transaction $transaction): void
    {
        $feeTransactions = Transaction::query()
            ->where('business_id', $entry->business_id)
            ->where('id', '!=', $transaction->id)
            ->get()
            ->filter(function (Transaction $candidate) use ($entry, $transaction): bool {
                $meta = $candidate->meta ?? [];

                if (! is_array($meta)) {
                    return false;
                }

                if ((int) ($meta['fee_of_transaction_id'] ?? 0) === (int) $transaction->id) {
                    return true;
                }

                return (int) ($meta['converted_from_journal_entry_id'] ?? 0) === (int) $entry->id
                    && $candidate->type?->value === 'expense';
            });

        foreach ($feeTransactions as $feeTransaction) {
            $this->deleteTransaction->handle($user, $feeTransaction);
        }

        $this->deleteTransaction->handle($user, $transaction);
    }

    protected function userOwnsBusiness(User $user, int $businessId): bool
    {
        if ((int) $user->currentBusiness?->owner_id === (int) $user->id) {
            return true;
        }

        $membership = BusinessUser::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->first();

        return $membership !== null && $membership->hasRole(BusinessRole::Owner);
    }
}
