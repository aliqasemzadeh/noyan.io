<?php

namespace Tests\Feature;

use App\Enums\Catalog\PriceType;
use App\Enums\Catalog\ProductType;
use App\Enums\Catalog\ProductUnit;
use App\Enums\Catalog\StockMovementType;
use App\Enums\CategoryType;
use App\Models\Business;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductPriceHistory;
use App\Models\Catalog\ProductStockMovement;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_models_persist_with_price_history_and_stock_movement(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();

        $category = Category::factory()->product()->create([
            'business_id' => $business->id,
            'name' => 'Storage',
            'slug' => 'storage',
        ]);

        $brand = Brand::factory()->create([
            'business_id' => $business->id,
            'name' => 'Silicon Power',
            'slug' => 'silicon-power',
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'type' => ProductType::Goods,
            'name' => 'SSD 512GB',
            'slug' => 'ssd-512gb',
            'sku' => 'SSD-SP-512G',
            'unit' => ProductUnit::Piece,
            'purchase_price' => '1200000',
            'sale_price' => '1500000',
            'average_cost' => '1200000',
            'last_purchase_price' => '1200000',
            'stock_quantity' => '10',
            'reserved_quantity' => '2',
            'track_inventory' => true,
        ]);

        $priceHistory = ProductPriceHistory::factory()->create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'price_type' => PriceType::Sale,
            'old_price' => '1400000',
            'new_price' => '1500000',
            'changed_by' => $user->id,
            'effective_at' => now(),
        ]);

        $stockMovement = ProductStockMovement::factory()->create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'movement_type' => StockMovementType::Opening,
            'quantity' => '10',
            'unit_cost' => '1200000',
            'balance_after' => '10',
            'created_by' => $user->id,
            'occurred_at' => now(),
            'note' => 'Walk-in sale without party',
        ]);

        $this->assertTrue($business->products()->whereKey($product->id)->exists());
        $this->assertTrue($category->products()->whereKey($product->id)->exists());
        $this->assertTrue($brand->products()->whereKey($product->id)->exists());
        $this->assertTrue($product->priceHistories()->whereKey($priceHistory->id)->exists());
        $this->assertTrue($product->stockMovements()->whereKey($stockMovement->id)->exists());
        $this->assertSame('8.000000000000000000', $product->availableQuantity());
        $this->assertNull($stockMovement->reference_type);
        $this->assertNull($stockMovement->reference_id);
        $this->assertFalse(Schema::hasTable('product_categories'));
        $this->assertSame(CategoryType::Product, $category->type);
    }

    public function test_service_products_do_not_track_inventory_by_default_in_factory(): void
    {
        $product = Product::factory()->service()->create();

        $this->assertSame(ProductType::Service, $product->type);
        $this->assertFalse($product->track_inventory);
        $this->assertSame(ProductUnit::Hour, $product->unit);
    }

    public function test_digital_products_do_not_track_inventory_by_default_in_factory(): void
    {
        $product = Product::factory()->digital()->create();

        $this->assertSame(ProductType::Digital, $product->type);
        $this->assertFalse($product->track_inventory);
    }
}
