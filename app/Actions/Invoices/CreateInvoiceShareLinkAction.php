<?php

namespace App\Actions\Invoices;

use App\Models\Accounting\Invoice;
use App\Support\ShortLinkService;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class CreateInvoiceShareLinkAction
{
    public function __construct(
        private ShortLinkService $shortLinks,
    ) {}

    public function handle(Invoice $invoice): string
    {
        if (! $invoice->isFinalized()) {
            throw ValidationException::withMessages([
                'invoice' => [__('general.invoice_share_requires_finalized')],
            ]);
        }

        $signedUrl = URL::signedRoute('invoices.public', [
            'invoice' => $invoice,
        ]);

        return $this->shortLinks->create(
            destination: $signedUrl,
            business: $invoice->business,
        );
    }
}
