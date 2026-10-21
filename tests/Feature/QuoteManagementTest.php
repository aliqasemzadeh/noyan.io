<?php

namespace Tests\Feature;

use App\Actions\Quotes\FinalizeQuoteAction;
use App\Actions\Quotes\SaveQuoteAction;
use App\Models\Accounting\Party;
use App\Models\Accounting\Quote;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_is_scoped_to_current_business(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $other = Business::factory()->create();

        Quote::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'quote_number' => 'QT-1405-01-0001',
        ]);

        Quote::factory()->create([
            'business_id' => $other->id,
            'created_by' => $user->id,
            'quote_number' => 'QT-1405-01-9999',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.quote.index')
            ->assertSee('QT-1405-01-0001')
            ->assertDontSee('QT-1405-01-9999');
    }

    #[Test]
    public function status_filter_finds_expired_quotes(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        Quote::factory()->expired()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'quote_number' => 'QT-1405-01-EX01',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.quote.index')
            ->set('statusFilter', 'expired')
            ->assertSee('QT-1405-01-EX01');
    }

    #[Test]
    public function copy_share_link_creates_short_link(): void
    {
        Config::set('short-link.short_url', 'https://short.test');

        [$user, $business] = $this->actingBusinessUser();
        $quote = Quote::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.quote.index')
            ->call('copyShareLink', $quote->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('short_links', [
            'business_id' => $business->id,
        ]);
    }

    #[Test]
    public function convert_redirects_to_invoice_edit(): void
    {
        [$user, $business, $party, $product] = $this->seedContext();

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

        $quote = app(FinalizeQuoteAction::class)->handle($quote, $user);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.quote.index')
            ->call('convert', $quote->id)
            ->assertRedirect(route('accounting.invoices.edit', $quote->fresh()->invoice_id));
    }

    #[Test]
    public function edit_page_forbids_converted_quote(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $quote = Quote::factory()->converted()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('accounting.quotes.edit', $quote))
            ->assertForbidden();
    }

    #[Test]
    public function edit_page_returns_not_found_for_other_business(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $other = Business::factory()->create();
        $quote = Quote::factory()->create([
            'business_id' => $other->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('accounting.quotes.edit', $quote))
            ->assertNotFound();
    }

    #[Test]
    public function form_validates_valid_until_after_issue_date(): void
    {
        [$user] = $this->actingBusinessUser();

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.quote.form')
            ->set('form.party_name', 'Guest')
            ->set('form.issue_date', now()->toDateString())
            ->set('form.valid_until', now()->subDay()->toDateString())
            ->call('saveAsDraft')
            ->assertHasErrors(['form.valid_until']);
    }

    #[Test]
    public function dashboard_shows_quote_shortcuts(): void
    {
        [$user] = $this->actingBusinessUser();

        $this->actingAs($user)
            ->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertSee(__('general.new_quote'))
            ->assertSee(__('general.quotes'));
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
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
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
