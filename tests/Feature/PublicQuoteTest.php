<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteShareLinkAction;
use App\Models\Accounting\Quote;
use App\Models\Accounting\QuoteItem;
use App\Models\Business;
use App\Models\ShortLink;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicQuoteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function signed_public_quote_page_renders_for_finalized_quote(): void
    {
        [$quote] = $this->makeFinalizedQuote([
            'phone' => '02112345678',
            'address' => 'Tehran, Iran',
        ]);

        $url = URL::signedRoute('quotes.public', ['quote' => $quote]);

        $this->get($url)
            ->assertOk()
            ->assertSee($quote->quote_number)
            ->assertSee('Tehran, Iran')
            ->assertSee(__('general.print_quote'));
    }

    #[Test]
    public function unsigned_public_quote_is_forbidden(): void
    {
        [$quote] = $this->makeFinalizedQuote();

        $this->get(route('quotes.public', $quote))
            ->assertForbidden();
    }

    #[Test]
    public function draft_quote_public_page_returns_not_found(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $quote = Quote::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'finalized_at' => null,
        ]);

        $url = URL::signedRoute('quotes.public', ['quote' => $quote]);

        $this->get($url)->assertNotFound();
    }

    #[Test]
    public function expired_quote_shows_expired_notice(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $quote = Quote::factory()->expired()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
        ]);

        QuoteItem::factory()->create([
            'quote_id' => $quote->id,
            'title' => RichText::fromPlain('Item'),
        ]);

        $url = URL::signedRoute('quotes.public', ['quote' => $quote]);

        $this->get($url)
            ->assertOk()
            ->assertSee(__('general.quote_expired_notice'));
    }

    #[Test]
    public function rich_titles_are_sanitized_on_public_page(): void
    {
        [$quote] = $this->makeFinalizedQuote();

        $quote->items()->first()->forceFill([
            'title' => '<p>Safe <script>alert(1)</script><strong>Item</strong></p>',
        ])->save();

        $url = URL::signedRoute('quotes.public', ['quote' => $quote->fresh()]);

        $response = $this->get($url)->assertOk();
        $response->assertSee('Safe', false);
        $response->assertSee('<strong>Item</strong>', false);
        $this->assertStringNotContainsString('<script>alert', $response->getContent());
    }

    #[Test]
    public function share_action_creates_short_link_for_finalized_quote(): void
    {
        Config::set('short-link.short_url', 'https://short.test');

        [$quote, $business] = $this->makeFinalizedQuote();

        $url = app(CreateQuoteShareLinkAction::class)->handle($quote->loadMissing('business'));

        $this->assertStringStartsWith('https://short.test/i/', $url);

        $shortLink = ShortLink::query()->where('business_id', $business->id)->first();

        $this->assertNotNull($shortLink);
        $this->assertStringContainsString('/quotes/public/'.$quote->id, $shortLink->destination);
    }

    #[Test]
    public function share_action_rejects_draft_quote(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $quote = Quote::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateQuoteShareLinkAction::class)->handle($quote);
    }

    /**
     * @param  array<string, mixed>  $businessOverrides
     * @return array{0: Quote, 1: Business, 2: User}
     */
    protected function makeFinalizedQuote(array $businessOverrides = []): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create($businessOverrides);

        $quote = Quote::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'party_name' => 'Customer',
            'items_total' => '1000',
            'total_amount' => '1000',
            'quote_number' => 'QT-1405-01-7777',
        ]);

        QuoteItem::factory()->create([
            'quote_id' => $quote->id,
            'title' => RichText::fromPlain('Service'),
            'quantity' => '1',
            'unit_price' => '1000',
            'total' => '1000',
        ]);

        return [$quote, $business, $user];
    }
}
