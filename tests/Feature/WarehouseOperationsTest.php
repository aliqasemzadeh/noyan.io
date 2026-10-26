<?php

namespace Tests\Feature;

use App\Actions\Inventory\ConfirmInboundReceiptAction;
use App\Actions\Inventory\ConfirmOutboundDispatchAction;
use App\Actions\Inventory\ReceiveStockTransferAction;
use App\Actions\Inventory\RecordStockCountAction;
use App\Actions\Inventory\ResolveStockCountLineAction;
use App\Actions\Inventory\ShipStockTransferAction;
use App\Actions\Invoices\FinalizeInvoiceAction;
use App\Actions\Invoices\SaveInvoiceAction;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Enums\Catalog\StockMovementType;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Catalog\InventoryStock;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductStockMovement;
use App\Models\Catalog\StockDocument;
use App\Models\Catalog\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WarehouseOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_finalization_reserves_stock_until_dispatch_is_confirmed(): void
    {
        [$user, $business, $party, $product] = $this->seedSaleContext(stock: '10');

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'title' => $product->name,
                'quantity' => '4',
                'unit_price' => '1500',
                'discount_amount' => '0',
                'tax_amount' => '0',
                'sort_order' => 0,
            ]],
        ]);

        app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '10', 18));
        $this->assertSame(0, bccomp((string) $product->fresh()->reserved_quantity, '4', 18));

        $document = StockDocument::query()->where('invoice_id', $invoice->id)->firstOrFail();

        app(ConfirmOutboundDispatchAction::class)->handle($document, $user);

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '6', 18));
        $this->assertSame(0, bccomp((string) $product->fresh()->reserved_quantity, '0', 18));
        $this->assertSame(StockMovementType::Sale, ProductStockMovement::query()->where('movement_type', StockMovementType::Sale)->first()?->movement_type);

        $this->expectException(ValidationException::class);

        app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'title' => $product->name,
                'quantity' => '1',
                'unit_price' => '1500',
                'discount_amount' => '0',
                'tax_amount' => '0',
                'sort_order' => 0,
            ]],
        ], $invoice->fresh());
    }

    public function test_purchase_receipt_can_record_a_short_delivery(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $party = Party::factory()->supplier()->create([
            'business_id' => $business->id,
            'balance' => '0',
        ]);
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '1',
        ]);

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Purchase->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'title' => $product->name,
                'quantity' => '5',
                'unit_price' => '800',
                'discount_amount' => '0',
                'tax_amount' => '0',
                'sort_order' => 0,
            ]],
        ]);

        app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '1', 18));

        $document = StockDocument::query()->with('lines')->where('invoice_id', $invoice->id)->firstOrFail();
        $line = $document->lines->first();

        app(ConfirmInboundReceiptAction::class)->handle($document, $user, [
            $line->id => '3',
        ]);

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '4', 18));
        $this->assertSame('discrepancy', $document->fresh()->status->value);
    }

    public function test_transfer_stays_in_transit_until_the_destination_confirms(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '9',
        ]);
        $source = $business->defaultWarehouse();
        $destination = Warehouse::query()
            ->where('business_id', $business->id)
            ->whereKeyNot($source?->id)
            ->firstOrFail();

        $document = app(ShipStockTransferAction::class)->handle(
            business: $business,
            product: $product,
            sourceWarehouseId: (int) $source?->id,
            destinationWarehouseId: (int) $destination->id,
            quantity: '2',
            userId: $user->id,
        );

        $this->assertSame('in_transit', $document->status->value);
        $this->assertSame(0, bccomp((string) InventoryStock::query()->where('product_id', $product->id)->where('warehouse_id', $source?->id)->value('physical_quantity'), '7', 18));
        $this->assertSame(0, bccomp((string) (InventoryStock::query()->where('product_id', $product->id)->where('warehouse_id', $destination->id)->value('physical_quantity') ?? '0'), '0', 18));

        app(ReceiveStockTransferAction::class)->handle($document, $user);

        $this->assertSame(0, bccomp((string) InventoryStock::query()->where('product_id', $product->id)->where('warehouse_id', $destination->id)->value('physical_quantity'), '2', 18));
        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '9', 18));
    }

    public function test_stock_count_adjustment_changes_physical_quantity(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '6',
        ]);
        $warehouse = $business->defaultWarehouse();

        $count = app(RecordStockCountAction::class)->handle(
            business: $business,
            warehouseId: (int) $warehouse?->id,
            lines: [[
                'product_id' => $product->id,
                'counted_quantity' => '4',
            ]],
            userId: $user->id,
        );

        $line = $count->lines()->firstOrFail();
        $this->assertSame('pending', $line->resolution->value);

        app(ResolveStockCountLineAction::class)->handle($line, $user, adjust: true);

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '4', 18));
        $this->assertSame('resolved', $count->fresh()->status->value);
    }

    public function test_warehouse_keeper_dashboard_hides_prices(): void
    {
        $owner = User::factory()->create();
        $keeper = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        BusinessUser::factory()->warehouseKeeper()->for($business)->for($keeper, 'user')->create();
        $keeper->forceFill(['current_business_id' => $business->id])->save();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '8',
            'purchase_price' => '987654',
            'sale_price' => '987654',
        ]);
        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_customer' => true,
            'balance' => '0',
        ]);

        $owner->forceFill(['current_business_id' => $business->id])->save();
        $invoice = app(SaveInvoiceAction::class)->handle($owner, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'title' => $product->name,
                'quantity' => '1',
                'unit_price' => '987654',
                'discount_amount' => '0',
                'tax_amount' => '0',
                'sort_order' => 0,
            ]],
        ]);
        app(FinalizeInvoiceAction::class)->handle($invoice, $owner);

        $this->assertFalse($keeper->canInBusiness(BusinessPermission::StockViewValue));
        $this->assertTrue(collect(BusinessRole::Accountant->applicablePermissions())->contains(BusinessPermission::StockViewValue));
        $this->assertFalse(collect(BusinessRole::WarehouseKeeper->applicablePermissions())->contains(BusinessPermission::StockViewValue));

        $this->actingAs($keeper)
            ->get(route('warehouse.dashboard'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertDontSee('987654');

        $document = StockDocument::query()->where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($keeper)
            ->get(route('warehouse.dispatches.index'))
            ->assertOk()
            ->assertSee($document->number)
            ->assertDontSee('987654');

        $this->actingAs($keeper)
            ->get(route('warehouse.dispatches.pick-list', $document))
            ->assertOk()
            ->assertSee($product->name)
            ->assertDontSee('987654');

        $this->actingAs($keeper)->get(route('warehouse.receipts.index'))->assertOk();
        $this->actingAs($keeper)->get(route('warehouse.transfers.index'))->assertOk();
        $this->actingAs($keeper)->get(route('warehouse.counts.index'))->assertOk();
        $this->actingAs($owner)->get(route('warehouse.movements.index'))->assertOk()->assertSee(__('general.unit_cost'));
        $this->actingAs($keeper)->get(route('warehouse.movements.index'))->assertOk()->assertDontSee(__('general.unit_cost'));
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
     * @return array{0: User, 1: Business, 2: Party, 3: Product}
     */
    protected function seedSaleContext(string $stock = '0'): array
    {
        [$user, $business] = $this->actingBusinessUser();

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_customer' => true,
            'balance' => '0',
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => $stock,
            'sale_price' => '1500',
        ]);

        return [$user, $business, $party, $product];
    }
}
