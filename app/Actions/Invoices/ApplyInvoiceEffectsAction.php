<?php

namespace App\Actions\Invoices;

use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\User;

class ApplyInvoiceEffectsAction
{
    public function handle(Invoice $invoice, User $user, bool $reverse = false): void
    {
        $invoice->loadMissing(['items.product', 'party', 'business']);

        if ($invoice->party_id === null || $invoice->party === null) {
            return;
        }

        $sign = $reverse ? '-1' : '1';

        /** @var Party $party */
        $party = $invoice->party;
        $amount = (string) $invoice->total_amount;

        $balanceDelta = match ($invoice->type) {
            InvoiceType::Sale => $amount,
            InvoiceType::Purchase => bcmul($amount, '-1', 18),
            InvoiceType::Return => bcmul($amount, '-1', 18),
        };

        $balanceDelta = bcmul($balanceDelta, $sign, 18);

        $party->forceFill([
            'balance' => bcadd((string) $party->balance, $balanceDelta, 18),
        ])->save();
    }
}
