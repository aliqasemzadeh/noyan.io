<?php

namespace App\Actions\Invoices;

use App\Actions\Inventory\AdjustStockAction;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\User;

class ApplyInvoiceEffectsAction
{
    public function __construct(
        protected AdjustStockAction $adjustStock,
    ) {}

    public function handle(Invoice $invoice, User $user, bool $reverse = false): void
    {
        $invoice->loadMissing(['items.product', 'party', 'business']);

        /** @var Business $business */
        $business = $invoice->business;

        $sign = $reverse ? '-1' : '1';

        foreach ($invoice->items as $item) {
            $product = $item->product;

            if ($product === null || ! $product->track_inventory) {
                continue;
            }

            $quantity = (string) $item->quantity;
            $baseDelta = match ($invoice->type) {
                InvoiceType::Sale => bcmul($quantity, '-1', 18),
                InvoiceType::Purchase => $quantity,
                InvoiceType::Return => $quantity,
            };

            $delta = bcmul($baseDelta, $sign, 18);

            if (bccomp($delta, '0', 18) === 0) {
                continue;
            }

            $this->adjustStock->handle(
                business: $business,
                product: $product,
                quantityDelta: $delta,
                movementType: match ($invoice->type) {
                    InvoiceType::Sale => StockMovementType::Sale,
                    InvoiceType::Purchase => StockMovementType::Purchase,
                    InvoiceType::Return => StockMovementType::ReturnIn,
                },
                unitCost: (string) $item->unit_price,
                reference: $invoice,
                note: ($reverse ? 'reverse:' : '').$invoice->invoice_number,
                userId: $user->id,
                allowNegative: $reverse,
            );
        }

        if ($invoice->party_id === null || $invoice->party === null) {
            return;
        }

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
