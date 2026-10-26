<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\ResolvesBusinessWarehouse;
use App\Enums\Catalog\StockMovementType;
use App\Models\Business;
use App\Models\Catalog\InventoryStock;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductStockMovement;
use App\Models\Catalog\Warehouse;
use App\Support\Warehouse\WarehouseDashboardCache;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustStockAction
{
    use ResolvesBusinessWarehouse;

    public function handle(
        Business $business,
        Product $product,
        string $quantityDelta,
        StockMovementType $movementType,
        ?int $warehouseId = null,
        ?string $unitCost = null,
        ?Model $reference = null,
        ?string $note = null,
        ?int $userId = null,
        ?int $projectId = null,
        ?int $costCenterId = null,
        bool $allowNegative = false,
        CarbonInterface|string|null $occurredAt = null,
    ): ProductStockMovement {
        if ((int) $product->business_id !== (int) $business->id) {
            throw ValidationException::withMessages([
                'product_id' => [__('general.product_not_found')],
            ]);
        }

        if (! $product->track_inventory) {
            throw ValidationException::withMessages([
                'product_id' => [__('general.product_does_not_track_inventory')],
            ]);
        }

        $quantityDelta = $this->normalizeDecimal($quantityDelta);

        if (bccomp($quantityDelta, '0', 18) === 0) {
            throw ValidationException::withMessages([
                'quantity' => [__('general.stock_quantity_must_be_nonzero')],
            ]);
        }

        $movement = DB::transaction(function () use (
            $business,
            $product,
            $quantityDelta,
            $movementType,
            $warehouseId,
            $unitCost,
            $reference,
            $note,
            $userId,
            $projectId,
            $costCenterId,
            $allowNegative,
            $occurredAt,
        ): ProductStockMovement {
            $warehouse = $this->resolveWarehouse($business, $warehouseId);

            /** @var Product $lockedProduct */
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $stock = $this->lockOrCreateStock($business, $lockedProduct, $warehouse);

            if ($movementType === StockMovementType::Reserve) {
                return $this->applyReserve(
                    $business,
                    $lockedProduct,
                    $stock,
                    $warehouse,
                    $quantityDelta,
                    $unitCost,
                    $reference,
                    $note,
                    $userId,
                    $projectId,
                    $costCenterId,
                    $occurredAt,
                );
            }

            if ($movementType === StockMovementType::ReleaseReserve) {
                return $this->applyReleaseReserve(
                    $business,
                    $lockedProduct,
                    $stock,
                    $warehouse,
                    $quantityDelta,
                    $unitCost,
                    $reference,
                    $note,
                    $userId,
                    $projectId,
                    $costCenterId,
                    $occurredAt,
                );
            }

            $newPhysical = bcadd((string) $stock->physical_quantity, $quantityDelta, 18);

            if (! $allowNegative && bccomp($newPhysical, '0', 18) === -1) {
                throw ValidationException::withMessages([
                    'quantity' => [__('general.insufficient_stock')],
                ]);
            }

            if (! $allowNegative && bccomp($newPhysical, (string) $stock->reserved_quantity, 18) === -1) {
                throw ValidationException::withMessages([
                    'quantity' => [__('general.insufficient_available_stock')],
                ]);
            }

            $stock->forceFill([
                'physical_quantity' => $newPhysical,
            ])->save();

            $movement = ProductStockMovement::query()->create([
                'business_id' => $business->id,
                'product_id' => $lockedProduct->id,
                'warehouse_id' => $warehouse->id,
                'project_id' => $projectId,
                'cost_center_id' => $costCenterId,
                'movement_type' => $movementType,
                'quantity' => $quantityDelta,
                'unit_cost' => $this->normalizeDecimal($unitCost ?? '0'),
                'balance_after' => $newPhysical,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'occurred_at' => $occurredAt ?? now(),
                'created_by' => $userId,
            ]);

            $lockedProduct->syncCachedStockTotals();

            return $movement;
        });

        WarehouseDashboardCache::bump((int) $business->id);

        return $movement;
    }

    protected function applyReserve(
        Business $business,
        Product $product,
        InventoryStock $stock,
        Warehouse $warehouse,
        string $quantityDelta,
        ?string $unitCost,
        ?Model $reference,
        ?string $note,
        ?int $userId,
        ?int $projectId,
        ?int $costCenterId,
        CarbonInterface|string|null $occurredAt,
    ): ProductStockMovement {
        $absolute = $this->absoluteQuantity($quantityDelta);

        if (bccomp($stock->availableQuantity(), $absolute, 18) === -1) {
            throw ValidationException::withMessages([
                'quantity' => [__('general.insufficient_available_stock')],
            ]);
        }

        $newReserved = bcadd((string) $stock->reserved_quantity, $absolute, 18);

        $stock->forceFill([
            'reserved_quantity' => $newReserved,
        ])->save();

        $movement = ProductStockMovement::query()->create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'project_id' => $projectId,
            'cost_center_id' => $costCenterId,
            'movement_type' => StockMovementType::Reserve,
            'quantity' => $absolute,
            'unit_cost' => $this->normalizeDecimal($unitCost ?? '0'),
            'balance_after' => (string) $stock->physical_quantity,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'occurred_at' => $occurredAt ?? now(),
            'created_by' => $userId,
        ]);

        $product->syncCachedStockTotals();

        return $movement;
    }

    protected function applyReleaseReserve(
        Business $business,
        Product $product,
        InventoryStock $stock,
        Warehouse $warehouse,
        string $quantityDelta,
        ?string $unitCost,
        ?Model $reference,
        ?string $note,
        ?int $userId,
        ?int $projectId,
        ?int $costCenterId,
        CarbonInterface|string|null $occurredAt,
    ): ProductStockMovement {
        $absolute = $this->absoluteQuantity($quantityDelta);
        $newReserved = bcsub((string) $stock->reserved_quantity, $absolute, 18);

        if (bccomp($newReserved, '0', 18) === -1) {
            throw ValidationException::withMessages([
                'quantity' => [__('general.insufficient_reserved_stock')],
            ]);
        }

        $stock->forceFill([
            'reserved_quantity' => $newReserved,
        ])->save();

        $movement = ProductStockMovement::query()->create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'project_id' => $projectId,
            'cost_center_id' => $costCenterId,
            'movement_type' => StockMovementType::ReleaseReserve,
            'quantity' => bcmul($absolute, '-1', 18),
            'unit_cost' => $this->normalizeDecimal($unitCost ?? '0'),
            'balance_after' => (string) $stock->physical_quantity,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'occurred_at' => $occurredAt ?? now(),
            'created_by' => $userId,
        ]);

        $product->syncCachedStockTotals();

        return $movement;
    }

    protected function lockOrCreateStock(Business $business, Product $product, Warehouse $warehouse): InventoryStock
    {
        InventoryStock::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
            ],
            [
                'business_id' => $business->id,
                'physical_quantity' => '0',
                'reserved_quantity' => '0',
            ],
        );

        return InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function absoluteQuantity(string $quantity): string
    {
        $normalized = $this->normalizeDecimal($quantity);
        $absolute = ltrim($normalized, '-');

        if (bccomp($absolute, '0', 18) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => [__('general.stock_quantity_must_be_positive')],
            ]);
        }

        return $absolute;
    }

    protected function normalizeDecimal(?string $value): string
    {
        $value = trim((string) ($value ?? '0'));
        $value = str_replace([',', ' '], ['', ''], $value);

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bcadd($value, '0', 18);
    }
}
