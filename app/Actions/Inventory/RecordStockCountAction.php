<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockCountResolution;
use App\Enums\Catalog\StockCountStatus;
use App\Models\Business;
use App\Models\Catalog\InventoryStock;
use App\Models\Catalog\Product;
use App\Models\Catalog\StockCount;
use App\Models\Catalog\StockCountLine;
use App\Services\Inventory\StockDocumentNumberGenerator;
use App\Support\Warehouse\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockCountAction
{
    public function __construct(
        protected StockDocumentNumberGenerator $numbers,
    ) {}

    /**
     * @param  list<array{product_id: int, counted_quantity: string|int|float}>  $lines
     */
    public function handle(Business $business, int $warehouseId, array $lines, ?int $userId = null, ?string $note = null): StockCount
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => [__('general.invoice_items_required')],
            ]);
        }

        return DB::transaction(function () use ($business, $warehouseId, $lines, $userId, $note): StockCount {
            $warehouseExists = $business->warehouses()->whereKey($warehouseId)->exists();

            if (! $warehouseExists) {
                throw ValidationException::withMessages([
                    'warehouse_id' => [__('general.warehouse_not_found')],
                ]);
            }

            $count = StockCount::query()->create([
                'business_id' => $business->id,
                'warehouse_id' => $warehouseId,
                'number' => $this->numbers->nextCountNumber($business->id),
                'status' => StockCountStatus::Open,
                'note' => $note,
                'counted_at' => now(),
                'created_by' => $userId,
            ]);

            $pending = 0;

            foreach ($lines as $index => $line) {
                $product = Product::query()
                    ->where('business_id', $business->id)
                    ->whereKey($line['product_id'])
                    ->where('track_inventory', true)
                    ->first();

                if ($product === null) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.counted_quantity" => [__('general.product_not_found')],
                    ]);
                }

                $counted = Quantity::normalize($line['counted_quantity']);

                if (bccomp($counted, '0', 18) === -1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.counted_quantity" => [__('general.stock_quantity_must_be_positive')],
                    ]);
                }

                $system = (string) (InventoryStock::query()
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->value('physical_quantity') ?? '0');
                $system = Quantity::normalize($system);
                $difference = bcsub($counted, $system, 18);
                $resolution = bccomp($difference, '0', 18) === 0
                    ? StockCountResolution::Accepted
                    : StockCountResolution::Pending;

                if ($resolution === StockCountResolution::Pending) {
                    $pending++;
                }

                StockCountLine::query()->create([
                    'stock_count_id' => $count->id,
                    'product_id' => $product->id,
                    'system_quantity' => $system,
                    'counted_quantity' => $counted,
                    'difference' => $difference,
                    'resolution' => $resolution,
                ]);
            }

            if ($pending === 0) {
                $count->forceFill(['status' => StockCountStatus::Resolved])->save();
            }

            return $count->fresh(['lines.product', 'warehouse']);
        });
    }
}
