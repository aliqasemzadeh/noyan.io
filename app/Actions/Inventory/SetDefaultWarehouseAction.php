<?php

namespace App\Actions\Inventory;

use App\Models\Business;
use App\Models\Catalog\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetDefaultWarehouseAction
{
    public function handle(Business $business, Warehouse $warehouse): Warehouse
    {
        if ((int) $warehouse->business_id !== (int) $business->id) {
            throw ValidationException::withMessages([
                'warehouse_id' => [__('general.warehouse_not_found')],
            ]);
        }

        return DB::transaction(function () use ($business, $warehouse): Warehouse {
            /** @var Warehouse $locked */
            $locked = Warehouse::query()
                ->whereKey($warehouse->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => [__('general.warehouse_inactive')],
                ]);
            }

            Warehouse::query()
                ->where('business_id', $business->id)
                ->where('is_default', true)
                ->whereKeyNot($locked->id)
                ->lockForUpdate()
                ->update(['is_default' => false]);

            $locked->forceFill([
                'is_default' => true,
            ])->save();

            return $locked->refresh();
        });
    }
}
