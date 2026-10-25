<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\ProductType;
use App\Enums\Catalog\ProductUnit;
use App\Models\Accounting\InvoiceItem;
use App\Models\Business;
use App\Models\Category;
use Database\Factories\Catalog\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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
        return $this->belongsTo(Category::class, 'category_id');
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
     * @return HasMany<InventoryStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    /**
     * @return BelongsToMany<Warehouse, $this>
     */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'inventory_stocks')
            ->withPivot(['physical_quantity', 'reserved_quantity', 'business_id'])
            ->withTimestamps();
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

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeWithTotalStock(Builder $query): Builder
    {
        return $query->addSelect([
            'total_physical_stock' => InventoryStock::query()
                ->selectRaw('COALESCE(SUM(physical_quantity), 0)')
                ->whereColumn('inventory_stocks.product_id', 'products.id'),
        ]);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeWithAvailableSellableStock(Builder $query): Builder
    {
        return $query->addSelect([
            'available_sellable_stock' => InventoryStock::query()
                ->selectRaw('COALESCE(SUM(inventory_stocks.physical_quantity - inventory_stocks.reserved_quantity), 0)')
                ->join('warehouses', 'warehouses.id', '=', 'inventory_stocks.warehouse_id')
                ->whereColumn('inventory_stocks.product_id', 'products.id')
                ->where('warehouses.is_sellable', true)
                ->whereNull('warehouses.deleted_at'),
        ]);
    }

    public function syncCachedStockTotals(): void
    {
        $totals = InventoryStock::query()
            ->where('product_id', $this->id)
            ->selectRaw('COALESCE(SUM(physical_quantity), 0) as physical_total')
            ->selectRaw('COALESCE(SUM(reserved_quantity), 0) as reserved_total')
            ->first();

        $this->forceFill([
            'stock_quantity' => $totals?->physical_total ?? '0',
            'reserved_quantity' => $totals?->reserved_total ?? '0',
        ])->save();
    }
}
