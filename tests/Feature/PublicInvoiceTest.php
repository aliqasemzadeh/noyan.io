<?php

namespace Tests\Feature;

use App\Actions\Invoices\CreateInvoiceShareLinkAction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Business;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PublicInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_public_invoice_page_renders_for_finalized_invoice(): void
    {
        [$invoice] = $this->makeFinalizedInvoice([
            'phone' => '02112345678',
            'address' => 'Tehran, Iran',
            'invoice_primary_color' => '#0ea5e9',
            'invoice_secondary_color' => '#0369a1',
        ]);

        $url = URL::signedRoute('invoices.public', ['invoice' => $invoice]);

        $this->get($url)
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Tehran, Iran')
            ->assertSee('02112345678')
            ->assertSee(__('general.print_invoice'));
    }

    public function test_public_invoice_works_without_logo_and_address(): void
    {
        [$invoice, $business] = $this->makeFinalizedInvoice([
            'phone' => null,
            'address' => null,
        ]);

        $url = URL::signedRoute('invoices.public', ['invoice' => $invoice]);

        $this->get($url)
            ->assertOk()
            ->assertSee($business->name)
            ->assertSee($invoice->invoice_number)
            ->assertSee(__('general.print_invoice'))
            ->assertDontSee('Tehran, Iran');
    }

    public function test_unsigned_public_invoice_is_forbidden(): void
    {
        [$invoice] = $this->makeFinalizedInvoice();

        $this->get(route('invoices.public', $invoice))
            ->assertForbidden();
    }

    public function test_draft_invoice_public_page_returns_not_found(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $invoice = Invoice::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'finalized_at' => null,
        ]);

        $url = URL::signedRoute('invoices.public', ['invoice' => $invoice]);

        $this->get($url)->assertNotFound();
    }

    public function test_share_action_creates_short_link_for_finalized_invoice(): void
    {
        Config::set('short-link.short_url', 'https://short.test');

        [$invoice, $business] = $this->makeFinalizedInvoice();

        $url = app(CreateInvoiceShareLinkAction::class)->handle($invoice->loadMissing('business'));

        $this->assertStringStartsWith('https://short.test/i/', $url);

        $this->assertDatabaseHas('short_links', [
            'business_id' => $business->id,
            'hits' => 0,
        ]);

        $shortLink = ShortLink::query()->where('business_id', $business->id)->first();

        $this->assertNotNull($shortLink);
        $this->assertStringContainsString('/invoices/public/'.$invoice->id, $shortLink->destination);
        $this->assertStringContainsString('signature=', $shortLink->destination);
    }

    public function test_share_action_rejects_draft_invoice(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $invoice = Invoice::factory()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'finalized_at' => null,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateInvoiceShareLinkAction::class)->handle($invoice);
    }

    public function test_panel_can_copy_share_link_for_finalized_invoice(): void
    {
        Config::set('short-link.short_url', 'https://short.test');

        [$invoice, $business, $user] = $this->makeFinalizedInvoice();
        $user->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.invoice.index')
            ->call('copyShareLink', $invoice->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('short_links', [
            'business_id' => $business->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $businessOverrides
     * @return array{0: Invoice, 1: Business, 2: User}
     */
    protected function makeFinalizedInvoice(array $businessOverrides = []): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create($businessOverrides);

        $invoice = Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'party_name' => 'Customer',
            'items_total' => '1000',
            'total_amount' => '1000',
            'invoice_number' => 'SL-1405-01-7777',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => '1',
            'unit_price' => '1000',
            'total' => '1000',
        ]);

        return [$invoice, $business, $user];
    }
}
