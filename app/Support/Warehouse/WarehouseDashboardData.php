<?php

namespace App\Support\Warehouse;

use App\Enums\Catalog\StockCountResolution;
use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Catalog\InventoryStock;
use App\Models\Catalog\ProductStockMovement;
use App\Models\Catalog\StockCountLine;
use App\Models\Catalog\StockDocument;
use Illuminate\Support\Facades\Cache;
use Morilog\Jalali\Jalalian;

class WarehouseDashboardData
{
    /**
     * @return array{dispatches: int, receipts: int, low_stock: int, transfers: int, discrepancies: int}
     */
    public function kpis(int $businessId, ?int $warehouseId): array
    {
        return Cache::remember($this->key($businessId, $warehouseId, 'kpis'), 120, function () use ($businessId, $warehouseId): array {
            $documents = StockDocument::query()->where('business_id', $businessId);
            $stocks = InventoryStock::query()->where('business_id', $businessId);

            if ($warehouseId !== null) {
                $stocks->where('warehouse_id', $warehouseId);
            }

            return [
                'dispatches' => (clone $documents)
                    ->where('type', StockDocumentType::Outbound)
                    ->whereIn('status', [StockDocumentStatus::PendingPick, StockDocumentStatus::Packed])
                    ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
                    ->count(),
                'receipts' => (clone $documents)
                    ->where('type', StockDocumentType::Inbound)
                    ->where('status', StockDocumentStatus::Expected)
                    ->whereDate('expected_at', '<=', now()->toDateString())
                    ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
                    ->count(),
                'low_stock' => (clone $stocks)
                    ->whereHas('product', fn ($query) => $query->where('track_inventory', true)->where('min_stock', '>', 0))
                    ->whereRaw('physical_quantity - reserved_quantity < (select min_stock from products where products.id = inventory_stocks.product_id)')
                    ->count(),
                'transfers' => (clone $documents)
                    ->where('type', StockDocumentType::Transfer)
                    ->where('status', StockDocumentStatus::InTransit)
                    ->when($warehouseId !== null, fn ($query) => $query->touchingWarehouse($warehouseId))
                    ->count(),
                'discrepancies' => StockCountLine::query()
                    ->where('resolution', StockCountResolution::Pending)
                    ->where('difference', '!=', 0)
                    ->whereHas('stockCount', function ($query) use ($businessId, $warehouseId): void {
                        $query->where('business_id', $businessId)
                            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId));
                    })
                    ->count(),
            ];
        });
    }

    /**
     * @return list<array{date: string, inbound: float, outbound: float}>
     */
    public function trend(int $businessId, ?int $warehouseId, int $days): array
    {
        $days = in_array($days, [7, 30], true) ? $days : 7;

        return Cache::remember($this->key($businessId, $warehouseId, 'trend-'.$days), 300, function () use ($businessId, $warehouseId, $days): array {
            $start = now()->startOfDay()->subDays($days - 1);
            $buckets = [];

            for ($offset = 0; $offset < $days; $offset++) {
                $day = $start->copy()->addDays($offset);
                $buckets[$day->toDateString()] = [
                    'date' => Jalalian::fromCarbon($day)->format('m/d'),
                    'inbound' => 0.0,
                    'outbound' => 0.0,
                ];
            }

            $movements = ProductStockMovement::query()
                ->where('business_id', $businessId)
                ->where('occurred_at', '>=', $start)
                ->whereNotIn('movement_type', [StockMovementType::Reserve, StockMovementType::ReleaseReserve])
                ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
                ->get(['movement_type', 'quantity', 'occurred_at']);

            foreach ($movements as $movement) {
                $key = $movement->occurred_at->toDateString();

                if (! isset($buckets[$key])) {
                    continue;
                }

                $quantity = abs((float) $movement->quantity);
                $direction = $this->direction($movement->movement_type, (string) $movement->quantity);
                $buckets[$key][$direction] += $quantity;
            }

            return array_values($buckets);
        });
    }

    /**
     * @return list<array{name: string, sku: string, moves: int}>
     */
    public function fastMovers(int $businessId, ?int $warehouseId): array
    {
        return Cache::remember($this->key($businessId, $warehouseId, 'fast'), 300, function () use ($businessId, $warehouseId): array {
            $start = Jalalian::now()->getFirstDayOfMonth()->toCarbon()->startOfDay();

            return ProductStockMovement::query()
                ->select('product_id')
                ->selectRaw('COUNT(*) as moves')
                ->with('product:id,name,sku')
                ->where('business_id', $businessId)
                ->where('occurred_at', '>=', $start)
                ->whereIn('movement_type', [
                    StockMovementType::Sale,
                    StockMovementType::TransferOut,
                    StockMovementType::ReturnOut,
                    StockMovementType::Consume,
                ])
                ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
                ->groupBy('product_id')
                ->orderByDesc('moves')
                ->limit(10)
                ->get()
                ->map(fn (ProductStockMovement $movement): array => [
                    'name' => (string) ($movement->product?->name ?? ''),
                    'sku' => (string) ($movement->product?->sku ?? ''),
                    'moves' => (int) $movement->moves,
                ])
                ->all();
        });
    }

    /**
     * @return list<array{name: string, quantity: float}>
     */
    public function distribution(int $businessId, ?int $warehouseId): array
    {
        return Cache::remember($this->key($businessId, $warehouseId, 'distribution'), 300, function () use ($businessId, $warehouseId): array {
            return InventoryStock::query()
                ->select('warehouse_id')
                ->selectRaw('SUM(physical_quantity) as quantity')
                ->with('warehouse:id,name')
                ->where('business_id', $businessId)
                ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
                ->groupBy('warehouse_id')
                ->orderByDesc('quantity')
                ->get()
                ->map(fn (InventoryStock $stock): array => [
                    'name' => (string) ($stock->warehouse?->name ?? ''),
                    'quantity' => (float) $stock->quantity,
                ])
                ->all();
        });
    }

    protected function direction(StockMovementType $type, string $quantity): string
    {
        if ($type === StockMovementType::Adjustment) {
            return bccomp($quantity, '0', 18) === -1 ? 'outbound' : 'inbound';
        }

        return match ($type) {
            StockMovementType::Purchase, StockMovementType::ReturnIn, StockMovementType::TransferIn, StockMovementType::Produce, StockMovementType::Opening => 'inbound',
            default => 'outbound',
        };
    }

    protected function key(int $businessId, ?int $warehouseId, string $suffix): string
    {
        return 'warehouse-dashboard.'.$businessId.'.'.WarehouseDashboardCache::version($businessId).'.'.($warehouseId ?? 'all').'.'.$suffix;
    }
}
