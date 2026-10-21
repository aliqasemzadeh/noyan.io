<?php

namespace App\Actions\Quotes;

use App\Models\Accounting\Quote;
use App\Support\ShortLinkService;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class CreateQuoteShareLinkAction
{
    public function __construct(
        private ShortLinkService $shortLinks,
    ) {}

    public function handle(Quote $quote): string
    {
        if (! $quote->isFinalized()) {
            throw ValidationException::withMessages([
                'quote' => [__('general.quote_share_requires_finalized')],
            ]);
        }

        $signedUrl = URL::signedRoute('quotes.public', [
            'quote' => $quote,
        ]);

        return $this->shortLinks->create(
            destination: $signedUrl,
            business: $quote->business,
        );
    }
}
