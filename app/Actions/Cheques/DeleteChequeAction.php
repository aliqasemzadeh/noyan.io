<?php

namespace App\Actions\Cheques;

use App\Enums\Accounting\ChequeStatus;
use App\Models\Accounting\Cheque;
use App\Models\Accounting\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DeleteChequeAction
{
    public function handle(User $user, Cheque $cheque): void
    {
        $businessId = $user->current_business_id;

        if ($businessId === null) {
            throw new RuntimeException('Current business is required to delete a cheque.');
        }

        DB::transaction(function () use ($cheque, $businessId): void {
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

            if ($locked->status !== ChequeStatus::Registered) {
                throw ValidationException::withMessages([
                    'cheque' => [__('general.cheque_cannot_delete')],
                ]);
            }

            $party = Party::query()
                ->where('business_id', $businessId)
                ->whereKey($locked->party_id)
                ->lockForUpdate()
                ->first();

            if ($party === null) {
                throw ValidationException::withMessages([
                    'party_id' => [__('general.cheque_party_invalid')],
                ]);
            }

            $party->forceFill([
                'balance' => $locked->type->applyReversePartyBalance((string) $party->balance, (string) $locked->amount),
            ])->save();

            Party::forgetBalanceCache($businessId, (int) $party->id);
            Party::forgetOptionsCache($businessId);

            $locked->delete();

            Cheque::forgetAlertsCache($businessId);
        });
    }
}
