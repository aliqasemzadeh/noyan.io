<?php

namespace Database\Factories\Catalog;

use App\Enums\Catalog\ProductType;
use App\Enums\Catalog\ProductUnit;
use App\Models\Business;
use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $sku = strtoupper(fake()->unique()->bothify('PRD-####-??'));

        return [
            'business_id' => Business::factory(),
            'category_id' => null,
            'brand_id' => null,
            'type' => ProductType::Goods,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'sku' => $sku,
            'barcode' => fake()->optional()->ean13(),
            'unit' => ProductUnit::Piece,
            'description' => fake()->optional()->sentence(),
            'purchase_price' => '100000',
            'sale_price' => '150000',
            'average_cost' => '100000',
            'last_purchase_price' => '100000',
            'track_inventory' => true,
            'stock_quantity' => '0',
            'reserved_quantity' => '0',
            'min_stock' => '0',
            'max_stock' => null,
            'tax_rate' => null,
            'is_active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductType::Service,
            'track_inventory' => false,
            'unit' => ProductUnit::Hour,
            'stock_quantity' => '0',
            'reserved_quantity' => '0',
        ]);
    }
}
