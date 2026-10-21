<?php

namespace App\Actions\Business;

use App\Enums\Business\SmsWalletTransactionType;
use App\Models\Business;
use App\Models\BusinessSmsWalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DebitBusinessSmsWalletAction
{
    public function handle(Business $business, string $amount, ?string $reason = null, ?Model $reference = null): BusinessSmsWalletTransaction
    {
        if (bccomp($amount, '0', 2) !== 1) {
            throw new RuntimeException('SMS wallet debit amount must be positive.');
        }

        return DB::transaction(function () use ($business, $amount, $reason, $reference): BusinessSmsWalletTransaction {
            /** @var Business $locked */
            $locked = Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $locked->sms_wallet_balance, $amount, 2) === -1) {
                throw new RuntimeException(__('general.sms_wallet_insufficient_balance'));
            }

            $balanceAfter = bcsub((string) $locked->sms_wallet_balance, $amount, 2);

            $locked->forceFill([
                'sms_wallet_balance' => $balanceAfter,
            ])->save();

            return BusinessSmsWalletTransaction::query()->create([
                'business_id' => $locked->id,
                'type' => SmsWalletTransactionType::Debit,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reason' => $reason ?: __('general.sms_wallet_sms_debit'),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);
        });
    }
}
