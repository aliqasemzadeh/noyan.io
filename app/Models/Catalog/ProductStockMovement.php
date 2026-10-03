<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\StockMovementType;
use App\Models\Business;
use App\Models\User;
use Database\Factories\Catalog\ProductStockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'business_id',
    'product_id',
    'movement_type',
    'quantity',
    'unit_cost',
    'balance_after',
    'reference_type',
    'reference_id',
    'note',
    'occurred_at',
    'created_by',
])]
class ProductStockMovement extends Model
{
    /** @use HasFactory<ProductStockMovementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity' => 'decimal:18',
            'unit_cost' => 'decimal:18',
            'balance_after' => 'decimal:18',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ProductStockMovementFactory
    {
        return ProductStockMovementFactory::new();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
