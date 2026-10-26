<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockDocumentStatus;
use App\Models\Accounting\Invoice;
use App\Models\Catalog\Product;
use App\Models\Catalog\StockDocument;
use App\Support\Warehouse\Quantity;
use Illuminate\Validation\ValidationException;

class GuardInvoiceStockEditAction
{
    /**
     * @param  list<array{product_id?: int|null, quantity?: string|int|float}>  $incomingItems
     */
    public function handle(Invoice $invoice, array $incomingItems): void
    {
        $invoice->loadMissing('items.product');

        $current = $this->trackedTotals($invoice->items);
        $incoming = $this->trackedTotals($incomingItems);

        if ($this->sameTotals($current, $incoming)) {
            return;
        }

        $editable = StockDocument::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', [
                StockDocumentStatus::PendingPick->value,
                StockDocumentStatus::Packed->value,
                StockDocumentStatus::Expected->value,
            ])
            ->exists();

        if ($editable) {
            return;
        }

        throw ValidationException::withMessages([
            'items' => [__('general.invoice_stock_already_confirmed')],
        ]);
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array<int, string>
     */
    protected function trackedTotals(iterable $items): array
    {
        $totals = [];

        foreach ($items as $item) {
            $productId = is_array($item) ? ($item['product_id'] ?? null) : $item->product_id;

            if ($productId === null || $productId === '') {
                continue;
            }

            $productId = (int) $productId;
            $product = is_array($item)
                ? Product::query()->find($productId)
                : $item->product;

            if ($product === null || ! $product->track_inventory) {
                continue;
            }

            $quantity = Quantity::normalize(is_array($item) ? ($item['quantity'] ?? '0') : $item->quantity);
            $totals[$productId] = bcadd($totals[$productId] ?? '0', $quantity, 18);
        }

        ksort($totals);

        return $totals;
    }

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    protected function sameTotals(array $left, array $right): bool
    {
        if (array_keys($left) !== array_keys($right)) {
            return false;
        }

        foreach ($left as $productId => $quantity) {
            if (bccomp($quantity, $right[$productId], 18) !== 0) {
                return false;
            }
        }

        return true;
    }
}
