<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Catalog\StockDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManualStockOperationAction
{
    public function __construct(
        protected CreateStockDocumentAction $createStockDocument,
        protected ConfirmOutboundDispatchAction $confirmOutbound,
        protected ConfirmInboundReceiptAction $confirmInbound,
    ) {}

    public function receive(
        Business $business,
        Product $product,
        int $warehouseId,
        string $quantity,
        ?string $unitCost,
        ?string $note,
        User $user,
    ): StockDocument {
        return DB::transaction(function () use ($business, $product, $warehouseId, $quantity, $unitCost, $note, $user): StockDocument {
            $document = $this->createStockDocument->handle(
                business: $business,
                type: StockDocumentType::Inbound,
                status: StockDocumentStatus::Expected,
                warehouseId: $warehouseId,
                lines: [[
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost ?? '0',
                ]],
                userId: $user->id,
                note: $note,
            );

            $lineId = (int) $document->lines->first()->id;

            return $this->confirmInbound->handle($document, $user, [
                $lineId => $quantity,
            ]);
        });
    }

    public function issue(
        Business $business,
        Product $product,
        int $warehouseId,
        string $quantity,
        ?string $unitCost,
        ?string $note,
        User $user,
    ): StockDocument {
        return DB::transaction(function () use ($business, $product, $warehouseId, $quantity, $unitCost, $note, $user): StockDocument {
            $document = $this->createStockDocument->handle(
                business: $business,
                type: StockDocumentType::Outbound,
                status: StockDocumentStatus::PendingPick,
                warehouseId: $warehouseId,
                lines: [[
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost ?? '0',
                ]],
                userId: $user->id,
                note: $note,
                reserve: true,
            );

            return $this->confirmOutbound->handle($document, $user);
        });
    }
}
