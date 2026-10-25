<?php

namespace App\Models\Catalog;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\Catalog\InventoryStockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\LogsBusinessActivity;

#[Fillable([
    'business_id',
    'product_id',
    'warehouse_id',
    'physical_quantity',
    'reserved_quantity',
])]
class InventoryStock extends Model
{
    /** @use HasFactory<InventoryStockFactory> */
    use BelongsToBusiness, HasFactory, LogsBusinessActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'physical_quantity' => 'decimal:18',
            'reserved_quantity' => 'decimal:18',
        ];
    }

    protected static function newFactory(): InventoryStockFactory
    {
        return InventoryStockFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function availableQuantity(): string
    {
        return bcsub((string) $this->physical_quantity, (string) $this->reserved_quantity, 18);
    }
}
