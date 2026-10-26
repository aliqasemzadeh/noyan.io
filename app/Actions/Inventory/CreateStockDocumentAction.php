<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\ResolvesBusinessWarehouse;
use App\Enums\Catalog\StockDocumentPriority;
use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Catalog\StockDocument;
use App\Models\Catalog\StockDocumentLine;
use App\Services\Inventory\StockDocumentNumberGenerator;
use App\Support\Warehouse\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateStockDocumentAction
{
    use ResolvesBusinessWarehouse;

    public function __construct(
        protected StockDocumentNumberGenerator $numbers,
        protected AdjustStockAction $adjustStock,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: string, unit_cost?: string|null}>  $lines
     */
    public function handle(
        Business $business,
        StockDocumentType $type,
        StockDocumentStatus $status,
        int $warehouseId,
        array $lines,
        ?int $userId = null,
        ?int $destinationWarehouseId = null,
        ?int $partyId = null,
        ?int $invoiceId = null,
        ?string $counterpartyName = null,
        ?string $expectedAt = null,
        ?string $note = null,
        StockDocumentPriority $priority = StockDocumentPriority::Normal,
        bool $reserve = false,
    ): StockDocument {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'items' => [__('general.invoice_items_required')],
            ]);
        }

        if ($type === StockDocumentType::Transfer && $destinationWarehouseId === null) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => [__('general.warehouse_not_found')],
            ]);
        }

        if ($destinationWarehouseId !== null && $destinationWarehouseId === $warehouseId) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => [__('general.stock_transfer_same_warehouse')],
            ]);
        }

        return DB::transaction(function () use (
            $business,
            $type,
            $status,
            $warehouseId,
            $lines,
            $userId,
            $destinationWarehouseId,
            $partyId,
            $invoiceId,
            $counterpartyName,
            $expectedAt,
            $note,
            $priority,
            $reserve,
        ): StockDocument {
            $warehouse = $this->resolveWarehouse($business, $warehouseId);

            if ($destinationWarehouseId !== null) {
                $this->resolveWarehouse($business, $destinationWarehouseId);
            }

            $document = StockDocument::query()->create([
                'business_id' => $business->id,
                'number' => $this->numbers->nextDocumentNumber($business->id, $type),
                'type' => $type,
                'status' => $status,
                'priority' => $priority,
                'warehouse_id' => $warehouse->id,
                'destination_warehouse_id' => $destinationWarehouseId,
                'party_id' => $partyId,
                'invoice_id' => $invoiceId,
                'counterparty_name' => $counterpartyName,
                'expected_at' => $expectedAt,
                'note' => $note,
                'created_by' => $userId,
            ]);

            foreach (array_values($lines) as $index => $line) {
                $quantity = Quantity::normalize($line['quantity']);

                if (bccomp($quantity, '0', 18) !== 1) {
                    throw ValidationException::withMessages([
                        'quantity' => [__('general.stock_quantity_must_be_positive')],
                    ]);
                }

                /** @var Product $product */
                $product = Product::query()
                    ->where('business_id', $business->id)
                    ->whereKey($line['product_id'])
                    ->where('track_inventory', true)
                    ->firstOrFail();

                $documentLine = StockDocumentLine::query()->create([
                    'stock_document_id' => $document->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => Quantity::normalize($line['unit_cost'] ?? '0'),
                    'sort_order' => $index,
                ]);

                if ($reserve) {
                    $this->adjustStock->handle(
                        business: $business,
                        product: $product,
                        quantityDelta: $quantity,
                        movementType: StockMovementType::Reserve,
                        warehouseId: $warehouse->id,
                        unitCost: (string) $documentLine->unit_cost,
                        reference: $document,
                        note: $document->number,
                        userId: $userId,
                    );
                }
            }

            return $document->fresh(['lines.product', 'party', 'warehouse', 'destinationWarehouse', 'invoice']);
        });
    }
}
