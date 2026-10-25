<?php

namespace Database\Factories\Catalog;

use App\Enums\Catalog\StockConversionItemDirection;
use App\Models\Catalog\Product;
use App\Models\Catalog\StockConversion;
use App\Models\Catalog\StockConversionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockConversionItem>
 */
class StockConversionItemFactory extends Factory
{
    protected $model = StockConversionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_conversion_id' => StockConversion::factory(),
            'product_id' => Product::factory(),
            'direction' => StockConversionItemDirection::Input,
            'quantity' => '1',
        ];
    }
}
