<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Catalog\StockDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmOutboundDispatchAction
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

            if ($document->type !== StockDocumentType::Outbound || ! in_array($document->status, [StockDocumentStatus::PendingPick, StockDocumentStatus::Packed], true)) {
                throw ValidationException::withMessages([
                    'document' => [__('general.stock_document_not_open')],
                ]);
            }

            foreach ($document->lines as $line) {
                $product = $line->product;

                if ($product === null) {
                    continue;
                }

                $this->adjustStock->handle(
                    business: $document->business,
                    product: $product,
                    quantityDelta: (string) $line->quantity,
                    movementType: StockMovementType::ReleaseReserve,
                    warehouseId: (int) $document->warehouse_id,
                    unitCost: (string) $line->unit_cost,
                    reference: $document,
                    note: $document->number,
                    userId: $user->id,
                );

                $this->adjustStock->handle(
                    business: $document->business,
                    product: $product,
                    quantityDelta: bcmul((string) $line->quantity, '-1', 18),
                    movementType: StockMovementType::Sale,
                    warehouseId: (int) $document->warehouse_id,
                    unitCost: (string) $line->unit_cost,
                    reference: $document,
                    note: $document->number,
                    userId: $user->id,
                );
            }

            $document->forceFill([
                'status' => StockDocumentStatus::Dispatched,
                'completed_at' => now(),
            ])->save();

            return $document->fresh(['lines.product', 'party', 'warehouse']);
        });
    }
}
