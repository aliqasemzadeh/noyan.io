<?php

namespace Tests\Feature;

use App\Actions\Invoices\FinalizeInvoiceAction;
use App\Actions\Quotes\ConvertQuoteToInvoiceAction;
use App\Actions\Quotes\FinalizeQuoteAction;
use App\Actions\Quotes\SaveQuoteAction;
use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Party;
use App\Models\Accounting\Quote;
use App\Models\Accounting\QuoteItem;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConvertQuoteToInvoiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function conversion_creates_draft_sale_invoice_with_bidirectional_links(): void
    {
        [$user, $business, $party, $product, $quote] = $this->makeFinalizedQuote();

        $invoice = app(ConvertQuoteToInvoiceAction::class)->handle($quote, $user);

        $this->assertTrue($invoice->isDraft());
        $this->assertSame(InvoiceType::Sale, $invoice->type);
        $this->assertSame($quote->id, $invoice->quote_id);
        $this->assertSame(0, bccomp((string) $invoice->total_amount, (string) $quote->total_amount, 18));
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '10', 18));

        $quote->refresh();
        $this->assertTrue($quote->isConverted());
        $this->assertSame($invoice->id, $quote->invoice_id);
    }

    #[Test]
    public function second_conversion_fails(): void
    {
        [$user, $business, $party, $product, $quote] = $this->makeFinalizedQuote();

        app(ConvertQuoteToInvoiceAction::class)->handle($quote, $user);

        $this->expectException(ValidationException::class);

        app(ConvertQuoteToInvoiceAction::class)->handle($quote->fresh(), $user);
    }

    #[Test]
    public function draft_quote_cannot_be_converted(): void
    {
        [$user, $business, $party, $product] = $this->seedContext();

        $quote = app(SaveQuoteAction::class)->handle($user, $this->quotePayload($party, $product));

        $this->expectException(ValidationException::class);

        app(ConvertQuoteToInvoiceAction::class)->handle($quote, $user);
    }

    #[Test]
    public function stock_is_unchanged_until_converted_invoice_is_finalized(): void
    {
        [$user, $business, $party, $product, $quote] = $this->makeFinalizedQuote();

        $invoice = app(ConvertQuoteToInvoiceAction::class)->handle($quote, $user);

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '10', 18));

        app(FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '10', 18));
        $this->assertSame(0, bccomp((string) $product->fresh()->reserved_quantity, '2', 18));
        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '20000', 18));
    }

    #[Test]
    public function expired_quote_can_still_be_converted(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_customer' => true,
        ]);

        $quote = Quote::factory()->expired()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'party_id' => $party->id,
            'items_total' => '10000',
            'total_amount' => '10000',
        ]);

        QuoteItem::factory()->create([
            'quote_id' => $quote->id,
            'title' => RichText::fromPlain('Expired item'),
            'quantity' => '1',
            'unit_price' => '10000',
            'total' => '10000',
        ]);

        $invoice = app(ConvertQuoteToInvoiceAction::class)->handle($quote->fresh(['items']), $user);

        $this->assertTrue($invoice->isDraft());
        $this->assertTrue($quote->fresh()->isConverted());
    }

    #[Test]
    public function missing_product_becomes_free_text_item(): void
    {
        [$user, $business, $party, $product, $quote] = $this->makeFinalizedQuote();

        $product->delete();

        $invoice = app(ConvertQuoteToInvoiceAction::class)->handle($quote->fresh(['items']), $user);

        $this->assertNull($invoice->items()->first()->product_id);
        $this->assertNotSame('', $invoice->items()->first()->titlePlain());
    }

    #[Test]
    public function deleting_draft_invoice_releases_quote_for_reconversion(): void
    {
        [$user, $business, $party, $product, $quote] = $this->makeFinalizedQuote();

        $invoice = app(ConvertQuoteToInvoiceAction::class)->handle($quote, $user);
        $invoice->delete();

        $quote->refresh();
        $this->assertFalse($quote->isConverted());
        $this->assertNull($quote->invoice_id);
        $this->assertNull($quote->converted_at);
        $this->assertTrue($quote->canBeConverted());

        $again = app(ConvertQuoteToInvoiceAction::class)->handle($quote, $user);

        $this->assertNotSame($invoice->id, $again->id);
        $this->assertTrue($quote->fresh()->isConverted());
    }

    /**
     * @return array{0: User, 1: Business, 2: Party, 3: Product, 4: Quote}
     */
    protected function makeFinalizedQuote(): array
    {
        [$user, $business, $party, $product] = $this->seedContext();

        $quote = app(SaveQuoteAction::class)->handle($user, $this->quotePayload($party, $product));
        $quote = app(FinalizeQuoteAction::class)->handle($quote, $user);

        return [$user, $business, $party, $product, $quote];
    }

    /**
     * @return array{0: User, 1: Business, 2: Party, 3: Product}
     */
    protected function seedContext(): array
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
            'stock_quantity' => '10',
            'sale_price' => '10000',
        ]);

        return [$user, $business, $party, $product];
    }

    /**
     * @return array<string, mixed>
     */
    protected function quotePayload(Party $party, Product $product): array
    {
        return [
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(7)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => RichText::fromPlain($product->name),
                    'quantity' => '2',
                    'unit_price' => '10000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ];
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
