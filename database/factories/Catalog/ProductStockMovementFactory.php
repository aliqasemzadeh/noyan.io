<?php

namespace Database\Factories\Catalog;

use App\Enums\Catalog\StockMovementType;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductStockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStockMovement>
 */
class ProductStockMovementFactory extends Factory
{
    protected $model = ProductStockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'product_id' => Product::factory(),
            'movement_type' => StockMovementType::Opening,
            'quantity' => '10',
            'unit_cost' => '100000',
            'balance_after' => '10',
            'reference_type' => null,
            'reference_id' => null,
            'note' => null,
            'occurred_at' => now(),
            'created_by' => null,
        ];
    }
}
