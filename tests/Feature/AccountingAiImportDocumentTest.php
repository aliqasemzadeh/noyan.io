<?php

namespace Tests\Feature;

use App\Ai\Agents\AccountingAssistant;
use App\Ai\Tools\ImportInvoice;
use App\Ai\Tools\ImportQuote;
use App\Enums\Accounting\FormalSaleDocumentKind;
use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Accounting\Quote;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Currency;
use App\Models\User;
use App\Services\Imports\FormalSaleExcelParser;
use App\Support\LocaleDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AccountingAiImportDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_detects_quote_and_invoice_from_examples(): void
    {
        $parser = app(FormalSaleExcelParser::class);

        $quote = $parser->parse(base_path('examples/حضرت زینب 1860.xlsx'));
        $this->assertSame(FormalSaleDocumentKind::Quote, $quote->kind);
        $this->assertSame('مدرسه حضرت زینب (س)', $quote->partyName);
        $this->assertSame('2107-2', $quote->serial);
        $this->assertCount(1, $quote->items);
        $this->assertSame('اس اس دی اپیسر', $quote->items[0]['title']);
        $this->assertSame('18', $quote->items[0]['quantity']);
        $this->assertSame('63500000', $quote->items[0]['unit_price']);

        $invoice = $parser->parse(base_path('examples/ناحیه 2 فاکتور 1405-0002.xlsx'));
        $this->assertSame(FormalSaleDocumentKind::Invoice, $invoice->kind);
        $this->assertSame('آموزش و پرورش ناحیه 2', $invoice->partyName);
        $this->assertSame('1405-0002', $invoice->serial);
        $this->assertCount(6, $invoice->items);
        $this->assertSame('مادربرد H510mk fater', $invoice->items[0]['title']);
    }

    public function test_import_quote_preview_does_not_persist(): void
    {
        [$user, $business] = $this->actingBusinessOwner();

        $result = (string) (new ImportQuote)->handle(new Request([
            'confirmed' => false,
            'file_path' => 'examples/حضرت زینب 1860.xlsx',
        ]));

        $this->assertStringContainsString(__('general.quote'), $result);
        $this->assertStringContainsString('مدرسه حضرت زینب (س)', $result);
        $this->assertStringContainsString(__('general.ai_transaction_preview_ask_confirm'), $result);
        $this->assertDatabaseCount('quotes', 0);
        $this->assertSame($user->id, auth()->id());
        $this->assertSame($business->id, auth()->user()->current_business_id);
    }

    public function test_import_quote_creates_draft_and_links_matching_product(): void
    {
        [$user, $business] = $this->actingBusinessOwner();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'اس اس دی اپیسر',
            'sku' => 'SSD-APACER',
        ]);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'مدرسه حضرت زینب (س)',
            'is_customer' => true,
        ]);

        $result = (string) (new ImportQuote)->handle(new Request([
            'confirmed' => true,
            'file_path' => 'examples/حضرت زینب 1860.xlsx',
        ]));

        $quote = Quote::query()->with('items')->first();

        $this->assertNotNull($quote);
        $this->assertNull($quote->finalized_at);
        $this->assertSame($party->id, $quote->party_id);
        $this->assertCount(1, $quote->items);
        $this->assertSame($product->id, $quote->items->first()->product_id);
        $this->assertSame('18.000000000000000000', (string) $quote->items->first()->quantity);
        $this->assertStringContainsString($quote->quote_number, $result);
    }

    public function test_import_quote_leaves_product_unlinked_when_not_found(): void
    {
        $this->actingBusinessOwner();

        (new ImportQuote)->handle(new Request([
            'confirmed' => true,
            'file_path' => 'examples/دبیرستان نام آوران 1860.xlsx',
        ]));

        $quote = Quote::query()->with('items')->first();

        $this->assertNotNull($quote);
        $this->assertNull($quote->items->first()->product_id);
        $this->assertNull($quote->party_id);
        $this->assertSame('دبیرستان نام آوران', $quote->party_name);
    }

    public function test_import_invoice_creates_sale_draft_and_skips_header_row(): void
    {
        $this->actingBusinessOwner();

        $result = (string) (new ImportInvoice)->handle(new Request([
            'confirmed' => true,
            'file_path' => 'examples/ناحیه 2 فاکتور 1405-0002.xlsx',
        ]));

        $invoice = Invoice::query()->with('items')->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceType::Sale, $invoice->type);
        $this->assertNull($invoice->finalized_at);
        $this->assertCount(6, $invoice->items);
        $this->assertFalse($invoice->items->contains(fn ($item): bool => str_contains((string) $item->title, 'کیس کامپیوتر')));
        $this->assertStringContainsString($invoice->invoice_number, $result);
        $this->assertSame(
            LocaleDate::toStorageDate('1405/07/27'),
            $invoice->issue_date->toDateString(),
        );
    }

    public function test_import_quote_rejects_invoice_file(): void
    {
        $this->actingBusinessOwner();

        $result = (string) (new ImportQuote)->handle(new Request([
            'confirmed' => true,
            'file_path' => 'examples/ناحیه 2 فاکتور 1405-0002.xlsx',
        ]));

        $this->assertStringContainsString(__('general.invoice'), $result);
        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_accounting_assistant_instructions_mention_import_tools(): void
    {
        $this->actingBusinessOwner();

        $instructions = (string) (new AccountingAssistant)->instructions();

        $this->assertStringContainsString('import_quote', $instructions);
        $this->assertStringContainsString('import_invoice', $instructions);
        $this->assertStringContainsString('file_path=', $instructions);
    }

    /**
     * @return array{0: User, 1: Business}
     */
    protected function actingBusinessOwner(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'name' => 'فروشگاه تست',
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'decimal_places' => 0,
        ]);
        $business->activateCurrency($currency, '1', true);

        $this->actingAs($user);

        return [$user, $business];
    }
}
