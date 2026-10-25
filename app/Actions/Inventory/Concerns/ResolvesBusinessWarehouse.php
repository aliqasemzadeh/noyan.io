<?php

namespace App\Actions\Inventory\Concerns;

use App\Models\Business;
use App\Models\Catalog\Warehouse;
use Illuminate\Validation\ValidationException;

trait ResolvesBusinessWarehouse
{
    protected function resolveWarehouse(Business $business, ?int $warehouseId = null): Warehouse
    {
        if ($warehouseId !== null) {
            $warehouse = Warehouse::query()
                ->where('business_id', $business->id)
                ->whereKey($warehouseId)
                ->active()
                ->first();

            if ($warehouse === null) {
                throw ValidationException::withMessages([
                    'warehouse_id' => [__('general.warehouse_not_found')],
                ]);
            }

            return $warehouse;
        }

        $warehouse = Warehouse::query()
            ->where('business_id', $business->id)
            ->default()
            ->active()
            ->first();

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => [__('general.default_warehouse_missing')],
            ]);
        }

        return $warehouse;
    }
}
