<?php

namespace App\Actions\Business;

use App\Enums\Business\SmsWalletTransactionType;
use App\Models\Business;
use App\Models\BusinessSmsWalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChargeBusinessSmsWalletAction
{
    public function handle(Business $business, string $amount, User $admin, ?string $reason = null): BusinessSmsWalletTransaction
    {
        if (bccomp($amount, '0', 2) !== 1) {
            throw ValidationException::withMessages([
                'amount' => [__('general.sms_wallet_amount_invalid')],
            ]);
        }

        return DB::transaction(function () use ($business, $amount, $admin, $reason): BusinessSmsWalletTransaction {
            /** @var Business $locked */
            $locked = Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();

            $balanceAfter = bcadd((string) $locked->sms_wallet_balance, $amount, 2);

            $locked->forceFill([
                'sms_wallet_balance' => $balanceAfter,
            ])->save();

            return BusinessSmsWalletTransaction::query()->create([
                'business_id' => $locked->id,
                'type' => SmsWalletTransactionType::Credit,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reason' => $reason ?: __('general.sms_wallet_manual_charge'),
                'created_by' => $admin->id,
            ]);
        });
    }
}
