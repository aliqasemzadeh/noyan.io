<?php

namespace App\Actions\Quotes;

use App\Models\Accounting\Quote;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DeleteQuoteAction
{
    public function handle(Quote $quote, User $user): void
    {
        if ((int) $quote->business_id !== (int) $user->current_business_id) {
            throw new RuntimeException('Quote does not belong to the current business.');
        }

        if (! $quote->isDeletable()) {
            throw ValidationException::withMessages([
                'quote' => [__('general.quote_cannot_delete_converted')],
            ]);
        }

        $quote->delete();
    }
}
