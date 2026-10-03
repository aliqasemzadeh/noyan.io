<?php

namespace Tests\Feature;

use App\Enums\Catalog\PriceType;
use App\Enums\Catalog\ProductType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Business;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductCategory;
use App\Models\Catalog\ProductPriceHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_product_with_opening_stock(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $category = ProductCategory::factory()->create([
            'business_id' => $business->id,
            'name' => 'Storage',
            'slug' => 'storage',
        ]);

        $brand = Brand::factory()->create([
            'business_id' => $business->id,
            'name' => 'Silicon Power',
            'slug' => 'silicon-power',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.catalog.product.create')
            ->set('form.type', ProductType::Goods->value)
            ->set('form.name', 'SSD 512GB')
            ->set('form.sku', 'SSD-SP-512G')
            ->set('form.category_id', $category->id)
            ->set('form.brand_id', $brand->id)
            ->set('form.purchase_price', '1200000')
            ->set('form.sale_price', '1500000')
            ->set('form.stock_quantity', '10')
            ->set('form.track_inventory', true)
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'SSD-SP-512G')->first();

        $this->assertNotNull($product);
        $this->assertSame($business->id, $product->business_id);
        $this->assertSame('10', $this->normalizeAmount((string) $product->stock_quantity));
        $this->assertDatabaseHas('product_stock_movements', [
            'product_id' => $product->id,
            'movement_type' => StockMovementType::Opening->value,
            'quantity' => 10,
        ]);
    }

    public function test_updating_sale_price_creates_price_history(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'SSD 512GB',
            'sku' => 'SSD-SP-512G',
            'purchase_price' => '1200000',
            'sale_price' => '1500000',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.catalog.product.edit')
            ->call('assignData', $product)
            ->set('form.sale_price', '1600000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_price_histories', [
            'product_id' => $product->id,
            'price_type' => PriceType::Sale->value,
            'old_price' => 1500000,
            'new_price' => 1600000,
            'changed_by' => $user->id,
        ]);

        $this->assertSame(
            1,
            ProductPriceHistory::query()->where('product_id', $product->id)->count()
        );
    }

    public function test_user_can_create_category_and_brand(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        Livewire::actingAs($user)
            ->test('accounting.catalog.category.create')
            ->set('form.name', 'Parts')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($user)
            ->test('accounting.catalog.brand.create')
            ->set('form.name', 'Logitech')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_categories', [
            'business_id' => $business->id,
            'name' => 'Parts',
        ]);

        $this->assertDatabaseHas('brands', [
            'business_id' => $business->id,
            'name' => 'Logitech',
        ]);
    }

    public function test_user_can_delete_product(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'sku' => 'DEL-001',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.catalog.product.delete')
            ->call('assignData', $product)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    public function test_catalog_index_pages_are_reachable(): void
    {
        [$user] = $this->actingBusinessUser();

        $this->actingAs($user)
            ->get(route('accounting.catalog.products.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('accounting.catalog.categories.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('accounting.catalog.brands.index'))
            ->assertOk();
    }

    public function test_product_form_can_create_category_and_brand_options(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $component = Livewire::actingAs($user)
            ->test('accounting.catalog.product.create')
            ->set('categorySearch', 'Storage Devices')
            ->call('createCategory')
            ->assertHasNoErrors()
            ->set('brandSearch', 'Samsung')
            ->call('createBrand')
            ->assertHasNoErrors();

        $category = ProductCategory::query()->where('name', 'Storage Devices')->first();
        $brand = Brand::query()->where('name', 'Samsung')->first();

        $this->assertNotNull($category);
        $this->assertNotNull($brand);
        $this->assertSame($business->id, $category->business_id);
        $this->assertSame($business->id, $brand->business_id);
        $this->assertSame($category->id, $component->get('form.category_id'));
        $this->assertSame($brand->id, $component->get('form.brand_id'));
    }

    public function test_user_can_create_digital_product_without_inventory(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        Livewire::actingAs($user)
            ->test('accounting.catalog.product.create')
            ->set('form.type', ProductType::Digital->value)
            ->set('form.name', 'License Key Pack')
            ->set('form.sku', 'DIG-LIC-001')
            ->set('form.purchase_price', '50000')
            ->set('form.sale_price', '90000')
            ->set('form.stock_quantity', '0')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', [
            'business_id' => $business->id,
            'sku' => 'DIG-LIC-001',
            'type' => ProductType::Digital->value,
            'track_inventory' => false,
        ]);
    }

    public function test_product_types_are_sorted_goods_digital_service(): void
    {
        $this->assertSame(
            [ProductType::Goods, ProductType::Digital, ProductType::Service],
            ProductType::sorted()
        );
    }

    /**
     * @return array{0: User, 1: Business}
     */
    protected function actingBusinessUser(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        return [$user, $business];
    }

    protected function normalizeAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount;
        }

        return rtrim(rtrim($amount, '0'), '.') ?: '0';
    }
}
