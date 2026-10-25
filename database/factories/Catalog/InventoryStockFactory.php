<?php

namespace Database\Factories\Catalog;

use App\Models\Business;
use App\Models\Catalog\InventoryStock;
use App\Models\Catalog\Product;
use App\Models\Catalog\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    protected $model = InventoryStock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'physical_quantity' => '0',
            'reserved_quantity' => '0',
        ];
    }
}
