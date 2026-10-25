<?php

namespace App\Actions\Invoices;

use App\Actions\Inventory\AdjustStockAction;
use App\Enums\Accounting\InvoiceNotificationType;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Catalog\StockMovementType;
use App\Jobs\Notifications\SendInvoiceNotificationJob;
use App\Models\Accounting\Invoice;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FinalizeInvoiceAction
{
    public function __construct(
        protected AdjustStockAction $adjustStock,
    ) {}

    public function handle(Invoice $invoice, User $user): Invoice
    {
        return DB::transaction(function () use ($invoice, $user): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->with(['items.product', 'party', 'business'])
                ->firstOrFail();

            if ((int) $invoice->business_id !== (int) $user->current_business_id) {
                throw new RuntimeException('Invoice does not belong to the current business.');
            }

            if ($invoice->isFinalized()) {
                throw ValidationException::withMessages([
                    'invoice' => [__('general.invoice_already_finalized')],
                ]);
            }

            if ($invoice->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => [__('general.invoice_items_required')],
                ]);
            }

            /** @var Business $business */
            $business = $invoice->business;

            foreach ($invoice->items as $item) {
                $product = $item->product;

                if ($product === null || ! $product->track_inventory) {
                    continue;
                }

                $quantity = (string) $item->quantity;
                $delta = match ($invoice->type) {
                    InvoiceType::Sale => bcmul($quantity, '-1', 18),
                    InvoiceType::Purchase => $quantity,
                    InvoiceType::Return => $quantity,
                };

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
                    note: $invoice->invoice_number,
                    userId: $user->id,
                );
            }

            if ($invoice->party_id !== null && $invoice->party !== null) {
                $party = $invoice->party;
                $amount = (string) $invoice->total_amount;

                $newBalance = match ($invoice->type) {
                    InvoiceType::Sale => bcadd((string) $party->balance, $amount, 18),
                    InvoiceType::Purchase => bcsub((string) $party->balance, $amount, 18),
                    InvoiceType::Return => bcsub((string) $party->balance, $amount, 18),
                };

                $party->forceFill([
                    'balance' => $newBalance,
                ])->save();
            }

            $buyerSnapshots = [];

            if ($invoice->party !== null) {
                $party = $invoice->party;
                $buyerSnapshots = [
                    'buyer_legal_name' => $party->legal_name ?: $party->displayName(),
                    'buyer_national_id' => $party->national_id,
                    'buyer_economic_code' => $party->economic_code,
                    'buyer_postal_code' => $party->postal_code,
                    'buyer_phone' => $party->mobile ?: $party->phone,
                    'buyer_address' => $party->address,
                ];
            }

            $invoice->forceFill([
                ...$buyerSnapshots,
                'finalized_at' => now(),
            ])->save();

            SendInvoiceNotificationJob::dispatch(
                $invoice->id,
                InvoiceNotificationType::NewInvoice->value,
            );

            return $invoice->fresh(['items', 'party']);
        });
    }
}
