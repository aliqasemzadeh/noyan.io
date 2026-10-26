<?php

namespace App\Actions\Inventory;

use App\Enums\Catalog\StockCountResolution;
use App\Enums\Catalog\StockCountStatus;
use App\Enums\Catalog\StockMovementType;
use App\Models\Catalog\StockCountLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveStockCountLineAction
{
    public function __construct(
        protected AdjustStockAction $adjustStock,
    ) {}

    public function handle(StockCountLine $line, User $user, bool $adjust): StockCountLine
    {
        return DB::transaction(function () use ($line, $user, $adjust): StockCountLine {
            /** @var StockCountLine $line */
            $line = StockCountLine::query()
                ->whereKey($line->id)
                ->lockForUpdate()
                ->with(['product', 'stockCount.business'])
                ->firstOrFail();

            if ($line->resolution !== StockCountResolution::Pending) {
                throw ValidationException::withMessages([
                    'line' => [__('general.stock_count_line_already_resolved')],
                ]);
            }

            $count = $line->stockCount;

            if ($adjust && $line->product !== null && bccomp((string) $line->difference, '0', 18) !== 0) {
                $this->adjustStock->handle(
                    business: $count->business,
                    product: $line->product,
                    quantityDelta: (string) $line->difference,
                    movementType: StockMovementType::Adjustment,
                    warehouseId: (int) $count->warehouse_id,
                    reference: $count,
                    note: $count->number,
                    userId: $user->id,
                    allowNegative: true,
                );
            }

            $line->forceFill([
                'resolution' => $adjust ? StockCountResolution::Adjusted : StockCountResolution::Accepted,
            ])->save();

            $stillPending = $count->lines()
                ->where('resolution', StockCountResolution::Pending->value)
                ->exists();

            if (! $stillPending) {
                $count->forceFill(['status' => StockCountStatus::Resolved])->save();
            }

            return $line->fresh(['product', 'stockCount']);
        });
    }
}
