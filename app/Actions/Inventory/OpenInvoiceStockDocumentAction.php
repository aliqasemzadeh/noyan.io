<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\ResolvesBusinessWarehouse;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Models\Accounting\Invoice;
use App\Models\Catalog\StockDocument;
use App\Models\User;
use App\Support\Warehouse\Quantity;

class OpenInvoiceStockDocumentAction
{
    use ResolvesBusinessWarehouse;

    public function __construct(
        protected CreateStockDocumentAction $createStockDocument,
    ) {}

    public function handle(Invoice $invoice, User $user): ?StockDocument
    {
        $invoice->loadMissing(['items.product', 'party', 'business']);

        $existing = StockDocument::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', '!=', StockDocumentStatus::Cancelled->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $lines = [];

        foreach ($invoice->items as $item) {
            $product = $item->product;

            if ($product === null || ! $product->track_inventory) {
                continue;
            }

            $quantity = Quantity::normalize((string) $item->quantity);

            if (bccomp($quantity, '0', 18) !== 1) {
                continue;
            }

            $lines[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => Quantity::normalize((string) $item->unit_price),
            ];
        }

        if ($lines === []) {
            return null;
        }

        $business = $invoice->business;
        $warehouse = $this->resolveWarehouse($business);
        $isSale = $invoice->type === InvoiceType::Sale;
        $counterparty = $invoice->party?->displayName() ?: (string) ($invoice->party_name ?? '');

        return $this->createStockDocument->handle(
            business: $business,
            type: $isSale ? StockDocumentType::Outbound : StockDocumentType::Inbound,
            status: $isSale ? StockDocumentStatus::PendingPick : StockDocumentStatus::Expected,
            warehouseId: $warehouse->id,
            lines: $lines,
            userId: $user->id,
            partyId: $invoice->party_id,
            invoiceId: $invoice->id,
            counterpartyName: $counterparty !== '' ? $counterparty : null,
            expectedAt: $isSale ? null : $invoice->issue_date?->toDateString(),
            note: $invoice->invoice_number,
            reserve: $isSale,
        );
    }
}
