<?php

namespace App\Actions\Cheques;

use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\ChequeStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Cheque;
use App\Models\Accounting\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ChangeChequeStatusAction
{
    public function __construct(
        private ProcessTransactionAction $processTransaction,
    ) {}

    /**
     * @param  array{
     *     transaction_date?: string|null,
     *     account_id?: int|null,
     *     reference_number?: string|null,
     *     note?: string|null
     * }  $data
     */
    public function handle(User $user, Cheque $cheque, ChequeStatus $to, array $data = []): Cheque
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to change cheque status.');
        }

        return DB::transaction(function () use ($user, $cheque, $to, $data, $businessId): Cheque {
            $locked = Cheque::query()
                ->where('business_id', $businessId)
                ->whereKey($cheque->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'cheque' => [__('general.cheque_not_found')],
                ]);
            }

            if (! $locked->status->canTransitionTo($to, $locked->type)) {
                throw ValidationException::withMessages([
                    'status' => [__('general.cheque_transition_not_allowed')],
                ]);
            }

            match ($to) {
                ChequeStatus::Deposited => $this->markDeposited($locked),
                ChequeStatus::Cleared => $this->markCleared($user, $locked, $data, $businessId),
                ChequeStatus::Bounced, ChequeStatus::Returned => $this->markReversed($locked, $to, $data, $businessId),
                ChequeStatus::Registered => throw ValidationException::withMessages([
                    'status' => [__('general.cheque_transition_not_allowed')],
                ]),
            };

            Cheque::forgetAlertsCache($businessId);

            return $locked->fresh(['party', 'account', 'transactions']);
        });
    }

    protected function markDeposited(Cheque $cheque): void
    {
        $cheque->forceFill([
            'status' => ChequeStatus::Deposited,
            'status_changed_at' => now(),
        ])->save();
    }

    /**
     * @param  array{
     *     transaction_date?: string|null,
     *     account_id?: int|null,
     *     reference_number?: string|null,
     *     note?: string|null
     * }  $data
     */
    protected function markCleared(User $user, Cheque $cheque, array $data, int $businessId): void
    {
        $accountId = $data['account_id'] ?? $cheque->account_id;

        if ($accountId === null) {
            throw ValidationException::withMessages([
                'account_id' => [__('general.cheque_account_required_to_clear')],
            ]);
        }

        $account = Account::query()
            ->where('business_id', $businessId)
            ->whereKey($accountId)
            ->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                'account_id' => [__('general.cheque_account_invalid')],
            ]);
        }

        $transactionDate = $data['transaction_date'] ?? now()->toDateString();
        $note = $data['note'] ?? $cheque->note ?? __('general.cheque').' '.$cheque->cheque_number;

        $this->processTransaction->handle($user, [
            'type' => $cheque->type->transactionType(),
            'account_id' => $account->id,
            'party_id' => $cheque->party_id,
            'invoice_id' => $cheque->invoice_id,
            'cheque_id' => $cheque->id,
            'transaction_date' => $transactionDate,
            'amount' => (string) $cheque->amount,
            'reference_number' => $data['reference_number'] ?? $cheque->cheque_number,
            'note' => $note,
            'skip_party_balance' => true,
        ]);

        $cheque->forceFill([
            'account_id' => $account->id,
            'status' => ChequeStatus::Cleared,
            'cleared_at' => $transactionDate,
            'status_changed_at' => now(),
        ])->save();
    }

    /**
     * @param  array{note?: string|null}  $data
     */
    protected function markReversed(Cheque $cheque, ChequeStatus $to, array $data, int $businessId): void
    {
        $party = Party::query()
            ->where('business_id', $businessId)
            ->whereKey($cheque->party_id)
            ->lockForUpdate()
            ->first();

        if ($party === null) {
            throw ValidationException::withMessages([
                'party_id' => [__('general.cheque_party_invalid')],
            ]);
        }

        $party->forceFill([
            'balance' => $cheque->type->applyReversePartyBalance((string) $party->balance, (string) $cheque->amount),
        ])->save();

        Party::forgetBalanceCache($businessId, (int) $party->id);
        Party::forgetOptionsCache($businessId);

        $note = $cheque->note;

        if (($data['note'] ?? null) !== null && $data['note'] !== '') {
            $note = trim(($note ? $note."\n" : '').(string) $data['note']);
        }

        $cheque->forceFill([
            'status' => $to,
            'status_changed_at' => now(),
            'party_reversed_at' => now(),
            'note' => $note,
        ])->save();
    }
}
