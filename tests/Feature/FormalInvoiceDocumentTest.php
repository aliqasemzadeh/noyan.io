<?php

namespace Tests\Feature;

use App\Actions\Business\UpdateBusinessAction;
use App\Actions\Invoices\FinalizeInvoiceAction;
use App\Actions\Invoices\SaveInvoiceAction;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Catalog\ProductUnit;
use App\Models\Accounting\Party;
use App\Models\Accounting\SaleKind;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\User;
use Database\Seeders\SaleKindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class FormalInvoiceDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_invoice_shows_seller_buyer_and_enriched_lines(): void
    {
        $this->seed(SaleKindSeeder::class);

        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'name' => 'Noyan Shop',
            'legal_name' => 'شرکت نویان',
            'phone' => '02112345678',
            'address' => 'Tehran, Iran',
            'national_id' => '14001234567',
            'economic_code' => '123456789012',
            'postal_code' => '1234567890',
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();

        $saleKind = SaleKind::query()->system()->where('code', 'formal')->firstOrFail();
        $business->activateSaleKind($saleKind);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'Buyer Co',
            'legal_name' => 'خریدار رسمی',
            'national_id' => '0013542419',
            'economic_code' => '987654321098',
            'phone' => '02187654321',
            'address' => 'Shiraz',
            'postal_code' => '7143678901',
            'is_customer' => true,
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Widget',
            'sku' => 'W-100',
            'unit' => ProductUnit::Piece,
            'sale_price' => '100000',
        ]);

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale,
            'sale_kind_id' => $saleKind->id,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'sku' => 'W-100',
                    'unit' => ProductUnit::Piece->value,
                    'quantity' => '2',
                    'unit_price' => '100000',
                    'discount_amount' => '10000',
                    'tax_amount' => '5000',
                ],
            ],
        ]);

        $invoice = app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $url = URL::signedRoute('invoices.public', ['invoice' => $invoice]);

        $this->get($url)
            ->assertOk()
            ->assertSee('شرکت نویان')
            ->assertSee('خریدار رسمی')
            ->assertSee('W-100')
            ->assertSee(__('general.document_business_details'))
            ->assertSee(__('general.seller_stamp_signature'))
            ->assertSee($saleKind->name)
            ->assertSee('02112345678');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'sale_kind_id' => $saleKind->id,
            'buyer_legal_name' => 'خریدار رسمی',
            'buyer_national_id' => '0013542419',
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'sku' => 'W-100',
            'unit' => ProductUnit::Piece->value,
        ]);
    }

    public function test_business_seller_identity_can_be_saved(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();

        app(UpdateBusinessAction::class)->handle($business, [
            'name' => $business->name,
            'type' => $business->type,
            'category' => $business->category,
            'legal_name' => 'نام حقوقی تست',
            'economic_code' => '123456789012',
            'postal_code' => '1234567890',
            'tax_memory_id' => 'TAX-MEM-1',
            'invoice_orientation' => $business->invoiceOrientation(),
        ]);

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'legal_name' => 'نام حقوقی تست',
            'economic_code' => '123456789012',
            'postal_code' => '1234567890',
            'tax_memory_id' => 'TAX-MEM-1',
        ]);
    }
}
