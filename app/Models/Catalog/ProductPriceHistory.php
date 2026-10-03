<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\PriceType;
use App\Models\Business;
use App\Models\User;
use Database\Factories\Catalog\ProductPriceHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id',
    'product_id',
    'price_type',
    'old_price',
    'new_price',
    'changed_by',
    'note',
    'effective_at',
])]
class ProductPriceHistory extends Model
{
    /** @use HasFactory<ProductPriceHistoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_type' => PriceType::class,
            'old_price' => 'decimal:18',
            'new_price' => 'decimal:18',
            'effective_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ProductPriceHistoryFactory
    {
        return ProductPriceHistoryFactory::new();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
