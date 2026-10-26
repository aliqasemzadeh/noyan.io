<?php

namespace Tests\Feature;

use App\Actions\Business\CreateBusinessAction;
use App\Actions\Inventory\AllocateStockAction;
use App\Actions\Inventory\ConvertStockAction;
use App\Actions\Inventory\SetDefaultWarehouseAction;
use App\Actions\Inventory\TransferStockAction;
use App\Actions\Invoices\FinalizeInvoiceAction;
use App\Actions\Invoices\SaveInvoiceAction;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessPanel;
use App\Enums\Business\BusinessType;
use App\Enums\Catalog\StockConversionItemDirection;
use App\Enums\Catalog\StockConversionStatus;
use App\Enums\Catalog\StockMovementType;
use App\Enums\Catalog\WarehouseType;
use App\Enums\CurrencyType;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\Party;
use App\Models\Accounting\Project;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Catalog\InventoryStock;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductStockMovement;
use App\Models\Catalog\StockConversion;
use App\Models\Catalog\Warehouse;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_business_seeds_default_and_preset_warehouses(): void
    {
        $user = User::factory()->create(['current_business_id' => null]);
        $currency = Currency::factory()->create([
            'code' => 'IRT',
            'type' => CurrencyType::Fiat,
            'is_system' => true,
            'business_id' => null,
            'is_active' => true,
        ]);

        $business = app(CreateBusinessAction::class)->handle($user, [
            'name' => 'انبار تست',
            'type' => BusinessType::Store->value,
            'category' => BusinessCategory::Computers->value,
            'currency_ids' => [$currency->id],
        ]);

        $warehouses = Warehouse::query()->where('business_id', $business->id)->get();

        $this->assertCount(4, $warehouses);
        $this->assertSame(1, $warehouses->where('is_default', true)->count());
        $this->assertTrue(
            $warehouses->firstWhere('is_default', true)?->type === WarehouseType::Main
        );
        $this->assertTrue($warehouses->contains(fn (Warehouse $w): bool => $w->type === WarehouseType::Project));
        $this->assertTrue($warehouses->contains(fn (Warehouse $w): bool => $w->type === WarehouseType::Production));
        $this->assertTrue($warehouses->contains(fn (Warehouse $w): bool => $w->type === WarehouseType::Consumable));
    }

    public function test_set_default_warehouse_flips_previous_default(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $main = $business->defaultWarehouse();
        $project = Warehouse::query()
            ->where('business_id', $business->id)
            ->ofType(WarehouseType::Project)
            ->firstOrFail();

        $this->assertTrue($main?->is_default);

        app(SetDefaultWarehouseAction::class)->handle($business, $project);

        $this->assertFalse($main->fresh()->is_default);
        $this->assertTrue($project->fresh()->is_default);
        $this->assertSame(1, Warehouse::query()->where('business_id', $business->id)->where('is_default', true)->count());
    }

    public function test_transfer_moves_stock_between_warehouses_and_keeps_product_total(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '10',
        ]);

        $main = $business->defaultWarehouse();
        $production = Warehouse::query()
            ->where('business_id', $business->id)
            ->ofType(WarehouseType::Production)
            ->firstOrFail();

        app(TransferStockAction::class)->handle(
            business: $business,
            product: $product,
            sourceWarehouseId: (int) $main->id,
            destinationWarehouseId: (int) $production->id,
            quantity: '4',
            userId: $user->id,
        );

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '10', 18));
        $this->assertSame(
            0,
            bccomp((string) InventoryStock::query()->where('product_id', $product->id)->where('warehouse_id', $main->id)->value('physical_quantity'), '6', 18)
        );
        $this->assertSame(
            0,
            bccomp((string) InventoryStock::query()->where('product_id', $product->id)->where('warehouse_id', $production->id)->value('physical_quantity'), '4', 18)
        );

        $this->assertDatabaseHas('product_stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $main->id,
            'movement_type' => StockMovementType::TransferOut->value,
        ]);
        $this->assertDatabaseHas('product_stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $production->id,
            'movement_type' => StockMovementType::TransferIn->value,
        ]);
    }

    public function test_allocate_to_project_transfers_into_project_warehouse(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '8',
        ]);
        $project = Project::factory()->create(['business_id' => $business->id]);

        app(AllocateStockAction::class)->handle(
            business: $business,
            product: $product,
            quantity: '3',
            projectId: $project->id,
            userId: $user->id,
        );

        $projectWarehouse = Warehouse::query()
            ->where('business_id', $business->id)
            ->ofType(WarehouseType::Project)
            ->firstOrFail();

        $this->assertSame(
            0,
            bccomp((string) InventoryStock::query()->where('product_id', $product->id)->where('warehouse_id', $projectWarehouse->id)->value('physical_quantity'), '3', 18)
        );
        $this->assertTrue(
            ProductStockMovement::query()
                ->where('product_id', $product->id)
                ->where('project_id', $project->id)
                ->where('movement_type', StockMovementType::TransferIn)
                ->exists()
        );
    }

    public function test_allocate_consume_against_cost_center_reduces_stock(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '5',
        ]);
        $costCenter = CostCenter::factory()->create(['business_id' => $business->id]);

        app(AllocateStockAction::class)->handle(
            business: $business,
            product: $product,
            quantity: '2',
            costCenterId: $costCenter->id,
            consume: true,
            userId: $user->id,
        );

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '3', 18));
        $this->assertDatabaseHas('product_stock_movements', [
            'product_id' => $product->id,
            'cost_center_id' => $costCenter->id,
            'movement_type' => StockMovementType::Consume->value,
        ]);
    }

    public function test_convert_stock_consumes_inputs_and_produces_outputs(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $rawA = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '10',
        ]);
        $rawB = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '10',
        ]);
        $finished = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '0',
        ]);

        $production = Warehouse::query()
            ->where('business_id', $business->id)
            ->ofType(WarehouseType::Production)
            ->firstOrFail();

        app(TransferStockAction::class)->handle($business, $rawA, (int) $business->defaultWarehouse()->id, (int) $production->id, '5');
        app(TransferStockAction::class)->handle($business, $rawB, (int) $business->defaultWarehouse()->id, (int) $production->id, '4');

        $conversion = StockConversion::query()->create([
            'business_id' => $business->id,
            'warehouse_id' => $production->id,
            'conversion_number' => 'SC-TEST-0001',
            'status' => StockConversionStatus::Draft,
            'occurred_at' => now(),
            'created_by' => $user->id,
        ]);

        $conversion->items()->createMany([
            ['product_id' => $rawA->id, 'direction' => StockConversionItemDirection::Input, 'quantity' => '2'],
            ['product_id' => $rawB->id, 'direction' => StockConversionItemDirection::Input, 'quantity' => '1'],
            ['product_id' => $finished->id, 'direction' => StockConversionItemDirection::Output, 'quantity' => '1'],
        ]);

        app(ConvertStockAction::class)->handle($conversion, $user->id);

        $this->assertTrue($conversion->fresh()->isPosted());
        $this->assertSame(
            0,
            bccomp((string) InventoryStock::query()->where('product_id', $rawA->id)->where('warehouse_id', $production->id)->value('physical_quantity'), '3', 18)
        );
        $this->assertSame(
            0,
            bccomp((string) InventoryStock::query()->where('product_id', $rawB->id)->where('warehouse_id', $production->id)->value('physical_quantity'), '3', 18)
        );
        $this->assertSame(
            0,
            bccomp((string) InventoryStock::query()->where('product_id', $finished->id)->where('warehouse_id', $production->id)->value('physical_quantity'), '1', 18)
        );
        $this->assertSame(0, bccomp((string) $finished->fresh()->stock_quantity, '1', 18));
    }

    public function test_convert_rejects_insufficient_input_stock(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $raw = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '1',
        ]);
        $finished = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '0',
        ]);
        $production = Warehouse::query()
            ->where('business_id', $business->id)
            ->ofType(WarehouseType::Production)
            ->firstOrFail();

        app(TransferStockAction::class)->handle($business, $raw, (int) $business->defaultWarehouse()->id, (int) $production->id, '1');

        $conversion = StockConversion::query()->create([
            'business_id' => $business->id,
            'warehouse_id' => $production->id,
            'conversion_number' => 'SC-TEST-0002',
            'status' => StockConversionStatus::Draft,
            'occurred_at' => now(),
            'created_by' => $user->id,
        ]);
        $conversion->items()->createMany([
            ['product_id' => $raw->id, 'direction' => StockConversionItemDirection::Input, 'quantity' => '5'],
            ['product_id' => $finished->id, 'direction' => StockConversionItemDirection::Output, 'quantity' => '1'],
        ]);

        $this->expectException(ValidationException::class);
        app(ConvertStockAction::class)->handle($conversion, $user->id);
    }

    public function test_finalize_invoice_uses_default_warehouse_and_skips_services(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_customer' => true,
            'balance' => '0',
        ]);
        $goods = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '10',
        ]);
        $service = Product::factory()->service()->create([
            'business_id' => $business->id,
        ]);

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $goods->id,
                    'title' => $goods->name,
                    'quantity' => '2',
                    'unit_price' => '1000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
                [
                    'product_id' => $service->id,
                    'title' => $service->name,
                    'quantity' => '1',
                    'unit_price' => '500',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 1,
                ],
            ],
        ]);

        app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->assertSame(0, bccomp((string) $goods->fresh()->stock_quantity, '10', 18));
        $this->assertSame(0, bccomp((string) $goods->fresh()->reserved_quantity, '2', 18));
        $this->assertSame(0, bccomp((string) $service->fresh()->stock_quantity, '0', 18));

        $movement = ProductStockMovement::query()->where('product_id', $goods->id)->first();
        $this->assertNotNull($movement);
        $this->assertSame((int) $business->defaultWarehouse()->id, (int) $movement->warehouse_id);
        $this->assertSame(StockMovementType::Reserve, $movement->movement_type);
        $this->assertSame(1, ProductStockMovement::query()->count());
    }

    public function test_warehouse_keeper_can_access_warehouse_panel_only(): void
    {
        [$keeper] = $this->actingWarehouseKeeper();

        $this->actingAs($keeper)
            ->get(route('warehouse.dashboard'))
            ->assertOk();

        $this->actingAs($keeper)
            ->get(route('accounting.dashboard'))
            ->assertRedirect(route('warehouse.dashboard'));

        $this->actingAs($keeper)
            ->get(route('sale.dashboard'))
            ->assertRedirect(route('warehouse.dashboard'));

        $this->assertTrue($keeper->canAccessPanel(BusinessPanel::Warehouse));
        $this->assertFalse($keeper->canAccessPanel(BusinessPanel::Accounting));
        $this->assertFalse($keeper->canAccessPanel(BusinessPanel::Sale));
    }

    public function test_seller_cannot_access_warehouse_panel(): void
    {
        $owner = User::factory()->create();
        $seller = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        BusinessUser::factory()
            ->seller()
            ->for($business)
            ->for($seller, 'user')
            ->create();

        $seller->forceFill(['current_business_id' => $business->id])->save();

        $this->actingAs($seller)
            ->get(route('warehouse.dashboard'))
            ->assertRedirect(route('sale.dashboard'));

        $this->assertFalse($seller->fresh()->canAccessPanel(BusinessPanel::Warehouse));
    }

    /**
     * @return array{0: User, 1: Business}
     */
    protected function actingBusinessUser(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        return [$user->fresh(), $business->fresh()];
    }

    /**
     * @return array{0: User, 1: Business}
     */
    protected function actingWarehouseKeeper(): array
    {
        $owner = User::factory()->create();
        $keeper = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        BusinessUser::factory()
            ->warehouseKeeper()
            ->for($business)
            ->for($keeper, 'user')
            ->create();

        $keeper->forceFill(['current_business_id' => $business->id])->save();

        return [$keeper->fresh(), $business];
    }
}
