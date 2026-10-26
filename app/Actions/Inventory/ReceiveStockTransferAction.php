<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Catalog\StockDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveStockTransferAction
{
    public function __construct(
        protected AdjustStockAction $adjustStock,
    ) {}

    public function handle(StockDocument $document, User $user): StockDocument
    {
        return DB::transaction(function () use ($document, $user): StockDocument {
            /** @var StockDocument $document */
            $document = StockDocument::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->with(['lines.product', 'business'])
                ->firstOrFail();

            if ($document->type !== StockDocumentType::Transfer || $document->status !== StockDocumentStatus::InTransit) {
                throw ValidationException::withMessages([
                    'document' => [__('general.stock_document_not_open')],
                ]);
            }

            foreach ($document->lines as $line) {
                if ($line->product === null) {
                    continue;
                }

                $this->adjustStock->handle(
                    business: $document->business,
                    product: $line->product,
                    quantityDelta: (string) $line->quantity,
                    movementType: StockMovementType::TransferIn,
                    warehouseId: (int) $document->destination_warehouse_id,
                    unitCost: (string) $line->unit_cost,
                    reference: $document,
                    note: $document->number,
                    userId: $user->id,
                );

                $line->forceFill([
                    'received_quantity' => $line->quantity,
                ])->save();
            }

            $document->forceFill([
                'status' => StockDocumentStatus::Received,
                'completed_at' => now(),
            ])->save();

            return $document->fresh(['lines.product', 'warehouse', 'destinationWarehouse']);
        });
    }
}
