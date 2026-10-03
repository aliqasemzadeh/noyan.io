<?php

namespace App\Actions\Invoices;

use App\Enums\Accounting\InvoiceType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Accounting\Invoice;
use App\Models\Catalog\ProductStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FinalizeInvoiceAction
{
    public function handle(Invoice $invoice, User $user): Invoice
    {
        return DB::transaction(function () use ($invoice, $user): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->with(['items.product', 'party'])
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

                $newStock = bcadd((string) $product->stock_quantity, $delta, 18);

                $product->forceFill([
                    'stock_quantity' => $newStock,
                ])->save();

                ProductStockMovement::query()->create([
                    'business_id' => $invoice->business_id,
                    'product_id' => $product->id,
                    'movement_type' => match ($invoice->type) {
                        InvoiceType::Sale => StockMovementType::Sale,
                        InvoiceType::Purchase => StockMovementType::Purchase,
                        InvoiceType::Return => StockMovementType::ReturnIn,
                    },
                    'quantity' => $delta,
                    'unit_cost' => $item->unit_price,
                    'balance_after' => $newStock,
                    'reference_type' => $invoice->getMorphClass(),
                    'reference_id' => $invoice->id,
                    'note' => $invoice->invoice_number,
                    'occurred_at' => now(),
                    'created_by' => $user->id,
                ]);
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

            $invoice->forceFill([
                'finalized_at' => now(),
            ])->save();

            return $invoice->fresh(['items', 'party']);
        });
    }
}
