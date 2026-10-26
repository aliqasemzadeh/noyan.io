<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Accounting\Invoice;
use App\Models\Catalog\Product;
use App\Models\Catalog\StockDocument;
use App\Models\Catalog\StockDocumentLine;
use App\Models\User;
use App\Support\Warehouse\Quantity;
use Illuminate\Support\Facades\DB;

class SyncInvoiceStockDocumentAction
{
    public function __construct(
        protected AdjustStockAction $adjustStock,
    ) {}

    public function handle(Invoice $invoice, User $user): void
    {
        $invoice->loadMissing(['items.product', 'party', 'business']);

        DB::transaction(function () use ($invoice, $user): void {
            $document = StockDocument::query()
                ->where('business_id', $invoice->business_id)
                ->where('invoice_id', $invoice->id)
                ->whereIn('status', [
                    StockDocumentStatus::PendingPick->value,
                    StockDocumentStatus::Packed->value,
                    StockDocumentStatus::Expected->value,
                ])
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                return;
            }

            $document->load('lines');

            $desired = [];

            foreach ($invoice->items as $item) {
                $product = $item->product;

                if ($product === null || ! $product->track_inventory) {
                    continue;
                }

                $quantity = Quantity::normalize((string) $item->quantity);

                if (bccomp($quantity, '0', 18) !== 1) {
                    continue;
                }

                $desired[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => Quantity::normalize((string) $item->unit_price),
                    'product' => $product,
                ];
            }

            $counterparty = $invoice->party?->displayName() ?: (string) ($invoice->party_name ?? '');

            $document->forceFill([
                'party_id' => $invoice->party_id,
                'counterparty_name' => $counterparty !== '' ? $counterparty : null,
                'expected_at' => $document->type === StockDocumentType::Inbound
                    ? $invoice->issue_date?->toDateString()
                    : null,
                'note' => $invoice->invoice_number,
            ])->save();

            if ($this->linesMatch($document, $desired)) {
                return;
            }

            if ($document->type === StockDocumentType::Outbound) {
                foreach ($document->lines as $line) {
                    $product = Product::query()->findOrFail($line->product_id);

                    $this->adjustStock->handle(
                        business: $invoice->business,
                        product: $product,
                        quantityDelta: (string) $line->quantity,
                        movementType: StockMovementType::ReleaseReserve,
                        warehouseId: (int) $document->warehouse_id,
                        unitCost: (string) $line->unit_cost,
                        reference: $document,
                        note: $document->number,
                        userId: $user->id,
                    );
                }
            }

            $document->lines()->delete();

            if ($desired === []) {
                $document->forceFill([
                    'status' => StockDocumentStatus::Cancelled,
                ])->save();

                return;
            }

            foreach ($desired as $index => $line) {
                StockDocumentLine::query()->create([
                    'stock_document_id' => $document->id,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'sort_order' => $index,
                ]);

                if ($document->type === StockDocumentType::Outbound) {
                    $this->adjustStock->handle(
                        business: $invoice->business,
                        product: $line['product'],
                        quantityDelta: $line['quantity'],
                        movementType: StockMovementType::Reserve,
                        warehouseId: (int) $document->warehouse_id,
                        unitCost: $line['unit_cost'],
                        reference: $document,
                        note: $document->number,
                        userId: $user->id,
                    );
                }
            }
        });
    }

    /**
     * @param  list<array{product_id: int, quantity: string, unit_cost: string, product: Product}>  $desired
     */
    protected function linesMatch(StockDocument $document, array $desired): bool
    {
        $current = $document->lines->values();

        if ($current->count() !== count($desired)) {
            return false;
        }

        foreach ($desired as $index => $line) {
            $existing = $current[$index] ?? null;

            if ($existing === null) {
                return false;
            }

            if ((int) $existing->product_id !== $line['product_id']) {
                return false;
            }

            if (bccomp((string) $existing->quantity, $line['quantity'], 18) !== 0) {
                return false;
            }
        }

        return true;
    }
}
