<?php

namespace Database\Factories\Catalog;

use App\Enums\Catalog\PriceType;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductPriceHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPriceHistory>
 */
class ProductPriceHistoryFactory extends Factory
{
    protected $model = ProductPriceHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'product_id' => Product::factory(),
            'price_type' => PriceType::Sale,
            'old_price' => '100000',
            'new_price' => '120000',
            'changed_by' => null,
            'note' => null,
            'effective_at' => now(),
        ];
    }
}
