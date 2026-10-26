<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\StockCountResolution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_count_id',
    'product_id',
    'system_quantity',
    'counted_quantity',
    'difference',
    'resolution',
])]
class StockCountLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:18',
            'counted_quantity' => 'decimal:18',
            'difference' => 'decimal:18',
            'resolution' => StockCountResolution::class,
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
