<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\ProductType;
use App\Enums\Catalog\ProductUnit;
use App\Models\Accounting\InvoiceItem;
use App\Models\Business;
use Database\Factories\Catalog\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id',
    'category_id',
    'brand_id',
    'type',
    'name',
    'slug',
    'sku',
    'barcode',
    'unit',
    'description',
    'purchase_price',
    'sale_price',
    'average_cost',
    'last_purchase_price',
    'track_inventory',
    'stock_quantity',
    'reserved_quantity',
    'min_stock',
    'max_stock',
    'tax_rate',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'unit' => ProductUnit::class,
            'purchase_price' => 'decimal:18',
            'sale_price' => 'decimal:18',
            'average_cost' => 'decimal:18',
            'last_purchase_price' => 'decimal:18',
            'track_inventory' => 'boolean',
            'stock_quantity' => 'decimal:18',
            'reserved_quantity' => 'decimal:18',
            'min_stock' => 'decimal:18',
            'max_stock' => 'decimal:18',
            'tax_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductPriceHistory, $this>
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    /**
     * @return HasMany<ProductStockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function availableQuantity(): string
    {
        return bcsub((string) $this->stock_quantity, (string) $this->reserved_quantity, 18);
    }
}
