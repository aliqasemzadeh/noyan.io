<?php

namespace App\Actions\Quotes;

use App\Models\Accounting\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FinalizeQuoteAction
{
    public function handle(Quote $quote, User $user): Quote
    {
        return DB::transaction(function () use ($quote, $user): Quote {
            /** @var Quote $quote */
            $quote = Quote::query()
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            if ((int) $quote->business_id !== (int) $user->current_business_id) {
                throw new RuntimeException('Quote does not belong to the current business.');
            }

            if ($quote->isFinalized()) {
                throw ValidationException::withMessages([
                    'quote' => [__('general.quote_already_finalized')],
                ]);
            }

            if ($quote->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => [__('general.quote_items_required')],
                ]);
            }

            $quote->forceFill([
                'finalized_at' => now(),
            ])->save();

            return $quote->fresh(['items', 'party']);
        });
    }
}
