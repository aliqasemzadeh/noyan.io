<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Catalog\StockDocument;
use Illuminate\Support\Facades\DB;

class ShipStockTransferAction
{
    public function __construct(
        protected CreateStockDocumentAction $createStockDocument,
        protected AdjustStockAction $adjustStock,
    ) {}

    public function handle(
        Business $business,
        Product $product,
        int $sourceWarehouseId,
        int $destinationWarehouseId,
        string $quantity,
        ?string $unitCost = null,
        ?string $note = null,
        ?int $userId = null,
    ): StockDocument {
        return DB::transaction(function () use (
            $business,
            $product,
            $sourceWarehouseId,
            $destinationWarehouseId,
            $quantity,
            $unitCost,
            $note,
            $userId,
        ): StockDocument {
            $document = $this->createStockDocument->handle(
                business: $business,
                type: StockDocumentType::Transfer,
                status: StockDocumentStatus::InTransit,
                warehouseId: $sourceWarehouseId,
                lines: [[
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost ?? '0',
                ]],
                userId: $userId,
                destinationWarehouseId: $destinationWarehouseId,
                note: $note,
            );

            $this->adjustStock->handle(
                business: $business,
                product: $product,
                quantityDelta: bcmul($quantity, '-1', 18),
                movementType: StockMovementType::TransferOut,
                warehouseId: $sourceWarehouseId,
                unitCost: $unitCost,
                reference: $document,
                note: $note ?: $document->number,
                userId: $userId,
            );

            return $document->fresh(['lines.product', 'warehouse', 'destinationWarehouse']);
        });
    }
}
