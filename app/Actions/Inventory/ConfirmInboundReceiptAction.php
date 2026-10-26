<?php

namespace App\Actions\Inventory;

use App\Enums\Accounting\InvoiceType;
use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Catalog\StockDocument;
use App\Models\User;
use App\Support\Warehouse\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmInboundReceiptAction
{
    public function __construct(
        protected AdjustStockAction $adjustStock,
    ) {}

    /**
     * @param  array<int, string|int|float>  $receivedByLineId
     */
    public function handle(StockDocument $document, User $user, array $receivedByLineId): StockDocument
    {
        return DB::transaction(function () use ($document, $user, $receivedByLineId): StockDocument {
            /** @var StockDocument $document */
            $document = StockDocument::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->with(['lines.product', 'business', 'invoice'])
                ->firstOrFail();

            if ($document->type !== StockDocumentType::Inbound || $document->status !== StockDocumentStatus::Expected) {
                throw ValidationException::withMessages([
                    'document' => [__('general.stock_document_not_open')],
                ]);
            }

            $movementType = $document->invoice?->type === InvoiceType::Return
                ? StockMovementType::ReturnIn
                : StockMovementType::Purchase;

            $hasDifference = false;

            foreach ($document->lines as $index => $line) {
                $received = Quantity::normalize($receivedByLineId[$line->id] ?? (string) $line->quantity);

                if (bccomp($received, '0', 18) === -1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.received_quantity" => [__('general.stock_quantity_must_be_positive')],
                    ]);
                }

                if (bccomp($received, (string) $line->quantity, 18) !== 0) {
                    $hasDifference = true;
                }

                $line->forceFill([
                    'received_quantity' => $received,
                ])->save();

                if ($line->product === null || bccomp($received, '0', 18) !== 1) {
                    continue;
                }

                $this->adjustStock->handle(
                    business: $document->business,
                    product: $line->product,
                    quantityDelta: $received,
                    movementType: $movementType,
                    warehouseId: (int) $document->warehouse_id,
                    unitCost: (string) $line->unit_cost,
                    reference: $document,
                    note: $document->number,
                    userId: $user->id,
                );
            }

            $document->forceFill([
                'status' => $hasDifference ? StockDocumentStatus::Discrepancy : StockDocumentStatus::Received,
                'completed_at' => now(),
            ])->save();

            return $document->fresh(['lines.product', 'party', 'warehouse']);
        });
    }
}
