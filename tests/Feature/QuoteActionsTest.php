<?php

namespace Tests\Feature;

use App\Actions\Quotes\DeleteQuoteAction;
use App\Actions\Quotes\FinalizeQuoteAction;
use App\Actions\Quotes\SaveQuoteAction;
use App\Models\Accounting\Party;
use App\Models\Accounting\Quote;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteActionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function saving_draft_assigns_number_and_totals_without_stock_changes(): void
    {
        [$user, $business, $party, $product] = $this->seedQuoteContext(stock: '10');

        $quote = app(SaveQuoteAction::class)->handle($user, [
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(7)->toDateString(),
            'global_discount' => '1000',
            'global_tax' => '500',
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
        ]);

        $this->assertNull($quote->finalized_at);
        $this->assertStringStartsWith('QT-', $quote->quote_number);
        $this->assertSame(0, bccomp((string) $quote->total_amount, '19500', 18));
        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '10', 18));
        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '0', 18));
    }

    #[Test]
    public function finalize_does_not_change_stock_or_party_balance(): void
    {
        [$user, $business, $party, $product] = $this->seedQuoteContext(stock: '10');

        $quote = app(SaveQuoteAction::class)->handle($user, [
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
        ]);

        $finalized = app(FinalizeQuoteAction::class)->handle($quote, $user);

        $this->assertNotNull($finalized->finalized_at);
        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '10', 18));
        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '0', 18));
    }

    #[Test]
    public function finalized_quote_cannot_be_edited(): void
    {
        [$user, $business, $party, $product] = $this->seedQuoteContext();

        $quote = app(SaveQuoteAction::class)->handle($user, [
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(7)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => RichText::fromPlain($product->name),
                    'quantity' => '1',
                    'unit_price' => '10000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ]);

        app(FinalizeQuoteAction::class)->handle($quote, $user);

        $this->expectException(ValidationException::class);

        app(SaveQuoteAction::class)->handle($user, [
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
        ], $quote->fresh());
    }

    #[Test]
    public function finalized_unconverted_quote_can_be_deleted(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $quote = Quote::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
        ]);

        app(DeleteQuoteAction::class)->handle($quote, $user);

        $this->assertSoftDeleted($quote);
    }

    #[Test]
    public function converted_quote_cannot_be_deleted(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $quote = Quote::factory()->converted()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
        ]);

        $this->expectException(ValidationException::class);

        app(DeleteQuoteAction::class)->handle($quote, $user);
    }

    /**
     * @return array{0: User, 1: Business, 2: Party, 3: Product}
     */
    protected function seedQuoteContext(string $stock = '0'): array
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
