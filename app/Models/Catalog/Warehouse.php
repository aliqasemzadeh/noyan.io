<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\WarehouseType;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\Catalog\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\LogsBusinessActivity;

#[Fillable([
    'business_id',
    'name',
    'code',
    'type',
    'is_default',
    'is_sellable',
    'is_active',
])]
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use BelongsToBusiness, HasFactory, LogsBusinessActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WarehouseType::class,
            'is_default' => 'boolean',
            'is_sellable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }

    /**
     * @return HasMany<InventoryStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    /**
     * @return HasMany<ProductStockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    /**
     * @return HasMany<StockConversion, $this>
     */
    public function stockConversions(): HasMany
    {
        return $this->hasMany(StockConversion::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_sellable', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOfType(Builder $query, WarehouseType|string $type): Builder
    {
        $value = $type instanceof WarehouseType ? $type->value : $type;

        return $query->where('type', $value);
    }
}
