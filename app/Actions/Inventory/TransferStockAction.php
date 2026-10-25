<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockMovementType;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductStockMovement;
use App\Models\Catalog\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferStockAction
{
    public function __construct(
        protected AdjustStockAction $adjustStock,
    ) {}

    /**
     * @return array{out: ProductStockMovement, in: ProductStockMovement}
     */
    public function handle(
        Business $business,
        Product $product,
        int $sourceWarehouseId,
        int $destinationWarehouseId,
        string $quantity,
        ?string $unitCost = null,
        ?string $note = null,
        ?int $userId = null,
        ?int $projectId = null,
        ?int $costCenterId = null,
    ): array {
        if ($sourceWarehouseId === $destinationWarehouseId) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => [__('general.stock_transfer_same_warehouse')],
            ]);
        }

        $quantity = $this->normalizePositive($quantity);

        return DB::transaction(function () use (
            $business,
            $product,
            $sourceWarehouseId,
            $destinationWarehouseId,
            $quantity,
            $unitCost,
            $note,
            $userId,
            $projectId,
            $costCenterId,
        ): array {
            $this->assertWarehouseBelongs($business, $sourceWarehouseId);
            $this->assertWarehouseBelongs($business, $destinationWarehouseId);

            $out = $this->adjustStock->handle(
                business: $business,
                product: $product,
                quantityDelta: bcmul($quantity, '-1', 18),
                movementType: StockMovementType::TransferOut,
                warehouseId: $sourceWarehouseId,
                unitCost: $unitCost,
                note: $note,
                userId: $userId,
                projectId: $projectId,
                costCenterId: $costCenterId,
            );

            $in = $this->adjustStock->handle(
                business: $business,
                product: $product,
                quantityDelta: $quantity,
                movementType: StockMovementType::TransferIn,
                warehouseId: $destinationWarehouseId,
                unitCost: $unitCost,
                note: $note,
                userId: $userId,
                projectId: $projectId,
                costCenterId: $costCenterId,
            );

            return [
                'out' => $out,
                'in' => $in,
            ];
        });
    }

    protected function assertWarehouseBelongs(Business $business, int $warehouseId): void
    {
        $exists = Warehouse::query()
            ->where('business_id', $business->id)
            ->whereKey($warehouseId)
            ->active()
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'warehouse_id' => [__('general.warehouse_not_found')],
            ]);
        }
    }

    protected function normalizePositive(string $quantity): string
    {
        $value = trim(str_replace([',', ' '], ['', ''], $quantity));

        if ($value === '' || ! is_numeric($value) || bccomp($value, '0', 18) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => [__('general.stock_quantity_must_be_positive')],
            ]);
        }

        return bcadd($value, '0', 18);
    }
}
