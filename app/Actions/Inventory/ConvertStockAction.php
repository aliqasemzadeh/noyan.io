<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockConversionItemDirection;
use App\Enums\Catalog\StockConversionStatus;
use App\Enums\Catalog\StockMovementType;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Catalog\StockConversion;
use App\Models\Catalog\StockConversionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertStockAction
{
    public function __construct(
        protected AdjustStockAction $adjustStock,
    ) {}

    public function handle(StockConversion $conversion, ?int $userId = null): StockConversion
    {
        return DB::transaction(function () use ($conversion, $userId): StockConversion {
            /** @var StockConversion $locked */
            $locked = StockConversion::query()
                ->whereKey($conversion->id)
                ->lockForUpdate()
                ->with(['items.product', 'warehouse', 'business'])
                ->firstOrFail();

            if ($locked->isPosted()) {
                throw ValidationException::withMessages([
                    'conversion' => [__('general.stock_conversion_already_posted')],
                ]);
            }

            $inputs = $locked->items->where('direction', StockConversionItemDirection::Input);
            $outputs = $locked->items->where('direction', StockConversionItemDirection::Output);

            if ($inputs->isEmpty() || $outputs->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => [__('general.stock_conversion_items_required')],
                ]);
            }

            /** @var Business $business */
            $business = $locked->business;

            foreach ($inputs as $item) {
                $this->assertTrackableItem($item);

                $this->adjustStock->handle(
                    business: $business,
                    product: $item->product,
                    quantityDelta: bcmul((string) $item->quantity, '-1', 18),
                    movementType: StockMovementType::Consume,
                    warehouseId: (int) $locked->warehouse_id,
                    reference: $locked,
                    note: $locked->conversion_number,
                    userId: $userId ?? $locked->created_by,
                    projectId: $locked->project_id,
                    costCenterId: $locked->cost_center_id,
                    occurredAt: $locked->occurred_at,
                );
            }

            foreach ($outputs as $item) {
                $this->assertTrackableItem($item);

                $this->adjustStock->handle(
                    business: $business,
                    product: $item->product,
                    quantityDelta: (string) $item->quantity,
                    movementType: StockMovementType::Produce,
                    warehouseId: (int) $locked->warehouse_id,
                    reference: $locked,
                    note: $locked->conversion_number,
                    userId: $userId ?? $locked->created_by,
                    projectId: $locked->project_id,
                    costCenterId: $locked->cost_center_id,
                    occurredAt: $locked->occurred_at,
                );
            }

            $locked->forceFill([
                'status' => StockConversionStatus::Posted,
                'posted_at' => now(),
            ])->save();

            return $locked->fresh(['items.product', 'warehouse', 'project', 'costCenter']);
        });
    }

    protected function assertTrackableItem(StockConversionItem $item): void
    {
        $product = $item->product;

        if (! $product instanceof Product || ! $product->track_inventory) {
            throw ValidationException::withMessages([
                'items' => [__('general.product_does_not_track_inventory')],
            ]);
        }

        if (bccomp((string) $item->quantity, '0', 18) !== 1) {
            throw ValidationException::withMessages([
                'items' => [__('general.stock_quantity_must_be_positive')],
            ]);
        }
    }
}
