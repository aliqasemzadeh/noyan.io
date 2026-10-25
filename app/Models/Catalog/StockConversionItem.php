<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\StockConversionItemDirection;
use Database\Factories\Catalog\StockConversionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\LogsBusinessActivity;

#[Fillable([
    'stock_conversion_id',
    'product_id',
    'direction',
    'quantity',
])]
class StockConversionItem extends Model
{
    /** @use HasFactory<StockConversionItemFactory> */
    use HasFactory, LogsBusinessActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => StockConversionItemDirection::class,
            'quantity' => 'decimal:18',
        ];
    }

    protected static function newFactory(): StockConversionItemFactory
    {
        return StockConversionItemFactory::new();
    }

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(StockConversion::class, 'stock_conversion_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isInput(): bool
    {
        return $this->direction === StockConversionItemDirection::Input;
    }

    public function isOutput(): bool
    {
        return $this->direction === StockConversionItemDirection::Output;
    }
}
