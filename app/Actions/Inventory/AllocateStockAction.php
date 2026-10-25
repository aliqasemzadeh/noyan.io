<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockMovementType;
use App\Enums\Catalog\WarehouseType;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\Project;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductStockMovement;
use App\Models\Catalog\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocateStockAction
{
    public function __construct(
        protected TransferStockAction $transferStock,
        protected AdjustStockAction $adjustStock,
    ) {}

    /**
     * Transfer stock into a PROJECT/PRODUCTION warehouse, or consume it against a dimension.
     *
     * @return array{out?: ProductStockMovement, in?: ProductStockMovement, movement?: ProductStockMovement}
     */
    public function handle(
        Business $business,
        Product $product,
        string $quantity,
        ?int $projectId = null,
        ?int $costCenterId = null,
        ?int $sourceWarehouseId = null,
        ?int $destinationWarehouseId = null,
        bool $consume = false,
        ?string $unitCost = null,
        ?string $note = null,
        ?int $userId = null,
    ): array {
        if ($projectId === null && $costCenterId === null) {
            throw ValidationException::withMessages([
                'project_id' => [__('general.stock_allocation_dimension_required')],
            ]);
        }

        $this->assertProject($business, $projectId);
        $this->assertCostCenter($business, $costCenterId);

        $quantity = $this->normalizePositive($quantity);

        return DB::transaction(function () use (
            $business,
            $product,
            $quantity,
            $projectId,
            $costCenterId,
            $sourceWarehouseId,
            $destinationWarehouseId,
            $consume,
            $unitCost,
            $note,
            $userId,
        ): array {
            $sourceId = $sourceWarehouseId ?? $this->defaultWarehouseId($business);

            if ($consume) {
                $movement = $this->adjustStock->handle(
                    business: $business,
                    product: $product,
                    quantityDelta: bcmul($quantity, '-1', 18),
                    movementType: StockMovementType::Consume,
                    warehouseId: $sourceId,
                    unitCost: $unitCost,
                    note: $note,
                    userId: $userId,
                    projectId: $projectId,
                    costCenterId: $costCenterId,
                );

                return ['movement' => $movement];
            }

            $destinationId = $destinationWarehouseId ?? $this->resolveTargetWarehouseId(
                $business,
                $projectId,
                $costCenterId,
            );

            return $this->transferStock->handle(
                business: $business,
                product: $product,
                sourceWarehouseId: $sourceId,
                destinationWarehouseId: $destinationId,
                quantity: $quantity,
                unitCost: $unitCost,
                note: $note,
                userId: $userId,
                projectId: $projectId,
                costCenterId: $costCenterId,
            );
        });
    }

    protected function resolveTargetWarehouseId(Business $business, ?int $projectId, ?int $costCenterId): int
    {
        $preferredType = $projectId !== null
            ? WarehouseType::Project
            : WarehouseType::Production;

        $warehouse = Warehouse::query()
            ->where('business_id', $business->id)
            ->ofType($preferredType)
            ->active()
            ->orderByDesc('is_default')
            ->first();

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => [__('general.warehouse_not_found')],
            ]);
        }

        return (int) $warehouse->id;
    }

    protected function defaultWarehouseId(Business $business): int
    {
        $warehouse = $business->defaultWarehouse();

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => [__('general.default_warehouse_missing')],
            ]);
        }

        return (int) $warehouse->id;
    }

    protected function assertProject(Business $business, ?int $projectId): void
    {
        if ($projectId === null) {
            return;
        }

        $exists = Project::query()
            ->where('business_id', $business->id)
            ->whereKey($projectId)
            ->active()
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'project_id' => [__('general.project_not_found')],
            ]);
        }
    }

    protected function assertCostCenter(Business $business, ?int $costCenterId): void
    {
        if ($costCenterId === null) {
            return;
        }

        $exists = CostCenter::query()
            ->where('business_id', $business->id)
            ->whereKey($costCenterId)
            ->active()
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'cost_center_id' => [__('general.cost_center_not_found')],
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
