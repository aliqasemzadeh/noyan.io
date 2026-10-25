<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\WarehouseType;
use App\Models\Business;
use App\Models\Catalog\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InitializeBusinessWarehousesAction
{
    /**
     * @return Collection<int, Warehouse>
     */
    public function handle(Business $business, bool $includePresets = true): Collection
    {
        return DB::transaction(function () use ($business, $includePresets): Collection {
            $types = $includePresets
                ? WarehouseType::businessPresets()
                : [WarehouseType::Main];

            $warehouses = collect();

            foreach ($types as $type) {
                $warehouse = Warehouse::query()->firstOrCreate(
                    [
                        'business_id' => $business->id,
                        'code' => $type->defaultCode(),
                    ],
                    [
                        'name' => $type->label(),
                        'type' => $type,
                        'is_default' => $type === WarehouseType::Main,
                        'is_sellable' => $type->isSellableByDefault(),
                        'is_active' => true,
                    ],
                );

                $warehouses->push($warehouse);
            }

            $hasDefault = $warehouses->contains(fn (Warehouse $warehouse): bool => $warehouse->is_default);

            if (! $hasDefault) {
                $main = $warehouses->first(
                    fn (Warehouse $warehouse): bool => $warehouse->type === WarehouseType::Main
                ) ?? $warehouses->first();

                if ($main !== null) {
                    $main->forceFill([
                        'is_default' => true,
                        'is_sellable' => true,
                    ])->save();
                }
            }

            return $warehouses->values();
        });
    }
}
