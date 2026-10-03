<?php

namespace Tests\Feature;

use App\Actions\Invoices\FinalizeInvoiceAction;
use App\Actions\Invoices\SaveInvoiceAction;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Catalog\StockMovementType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductStockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_draft_does_not_change_stock_or_balance(): void
    {
        [$user, $business, $party, $product] = $this->seedSaleContext();

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'global_discount' => '1000',
            'global_tax' => '500',
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '2',
                    'unit_price' => '10000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
                [
                    'product_id' => null,
                    'title' => 'Free text service',
                    'quantity' => '1',
                    'unit_price' => '3000',
                    'discount_amount' => '0',
                    'tax_amount' => '200',
                    'sort_order' => 1,
                ],
            ],
        ]);

        $this->assertNull($invoice->finalized_at);
        $this->assertSame('0', (string) $product->fresh()->stock_quantity);
        $this->assertSame('0', (string) $party->fresh()->balance);
        $this->assertSame(2, $invoice->items()->count());
        // (2*10000 + 3000+200) - 1000 + 500 = 22700
        $this->assertSame(0, bccomp((string) $invoice->total_amount, '22700', 18));
        $this->assertDatabaseCount('product_stock_movements', 0);
    }

    public function test_finalize_sale_decrements_stock_and_increases_party_balance(): void
    {
        [$user, $business, $party, $product] = $this->seedSaleContext(stock: '10');

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '3',
                    'unit_price' => '15000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ]);

        $finalized = app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->assertNotNull($finalized->finalized_at);
        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '7', 18));
        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '45000', 18));

        $movement = ProductStockMovement::query()->first();
        $this->assertNotNull($movement);
        $this->assertSame(StockMovementType::Sale, $movement->movement_type);
        $this->assertSame(0, bccomp((string) $movement->quantity, '-3', 18));
    }

    public function test_finalize_purchase_increments_stock_and_decreases_party_balance(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $party = Party::factory()->supplier()->create([
            'business_id' => $business->id,
            'balance' => '0',
        ]);
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '5',
            'purchase_price' => '8000',
        ]);

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Purchase->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '2',
                    'unit_price' => '8000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ]);

        app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '7', 18));
        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '-16000', 18));
        $this->assertDatabaseHas('product_stock_movements', [
            'product_id' => $product->id,
            'movement_type' => StockMovementType::Purchase->value,
        ]);
    }

    public function test_finalize_skips_stock_for_non_tracked_products(): void
    {
        [$user, $business, $party] = $this->seedSaleContext();
        $service = Product::factory()->service()->create([
            'business_id' => $business->id,
            'stock_quantity' => '0',
        ]);

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $service->id,
                    'title' => $service->name,
                    'quantity' => '1',
                    'unit_price' => '50000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ]);

        app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->assertDatabaseCount('product_stock_movements', 0);
        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '50000', 18));
    }

    public function test_second_finalize_fails(): void
    {
        [$user, , $party, $product] = $this->seedSaleContext(stock: '10');

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '1',
                    'unit_price' => '1000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ]);

        app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->expectException(ValidationException::class);
        app(FinalizeInvoiceAction::class)->handle($invoice->fresh(), $user);
    }

    public function test_guest_party_invoice_skips_balance_update(): void
    {
        [$user, $business, , $product] = $this->seedSaleContext(stock: '4');

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_name' => 'Walk-in Guest',
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '1',
                    'unit_price' => '2000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ]);

        $this->assertNull($invoice->party_id);
        $this->assertSame('Walk-in Guest', $invoice->party_name);

        app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '3', 18));
        $this->assertDatabaseCount('parties', 1);
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
            'sale_price' => '10000',
        ]);

        return [$user, $business, $party, $product];
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
}
