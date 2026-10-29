<?php

namespace Tests\Feature;

use App\Ai\Agents\AccountingAssistant;
use App\Ai\Tools\CreateInvoice;
use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\Currency;
use App\Models\User;
use App\Support\LocaleDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AccountingAiCreateInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_does_not_persist_sale_invoice(): void
    {
        [$user, $business] = $this->actingBusinessOwner();
        $this->ensureParty($business->id, 'مدرسه حضرت زینب (س)');

        $result = (string) (new CreateInvoice)->handle(new Request([
            'confirmed' => false,
            'type' => 'sale',
            'party_name' => 'مدرسه حضرت زینب (س)',
            'issue_date' => '1405/07/27',
            'items' => [
                [
                    'title' => 'اس اس دی اپیسر',
                    'quantity' => '۱۸',
                    'unit_price' => '63,500,000',
                ],
            ],
        ]));

        $this->assertStringContainsString(__('general.invoice_type_sale'), $result);
        $this->assertStringContainsString('مدرسه حضرت زینب (س)', $result);
        $this->assertStringContainsString('اس اس دی اپیسر', $result);
        $this->assertStringContainsString(__('general.ai_invoice_preview_ask_confirm'), $result);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame($user->id, auth()->id());
    }

    public function test_confirmed_creates_sale_draft_and_links_product(): void
    {
        [$user, $business] = $this->actingBusinessOwner();
        $party = $this->ensureParty($business->id, 'آموزش و پرورش ناحیه 2');
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'مادربرد H510mk fater',
            'is_active' => true,
        ]);

        $result = (string) (new CreateInvoice)->handle(new Request([
            'confirmed' => true,
            'type' => 'sale',
            'party_name' => 'آموزش و پرورش ناحیه 2',
            'issue_date' => '1405/07/27',
            'items' => [
                [
                    'title' => 'مادربرد H510mk fater',
                    'quantity' => '2',
                    'unit_price' => '1000000',
                ],
                [
                    'title' => 'رم ۸ گیگ',
                    'quantity' => '4',
                    'unit_price' => '500000',
                ],
            ],
        ]));

        $invoice = Invoice::query()->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceType::Sale, $invoice->type);
        $this->assertSame($party->id, $invoice->party_id);
        $this->assertNull($invoice->finalized_at);
        $this->assertSame('4000000', bcadd((string) $invoice->total_amount, '0', 0));
        $this->assertCount(2, $invoice->items);
        $this->assertSame($product->id, $invoice->items->firstWhere('title', 'مادربرد H510mk fater')?->product_id);
        $this->assertNull($invoice->items->firstWhere('title', 'رم ۸ گیگ')?->product_id);
        $this->assertStringContainsString($invoice->invoice_number, $result);
        $this->assertStringContainsString(__('general.invoice_type_sale'), $result);
        $this->assertSame($user->id, $invoice->created_by);
        $this->assertSame(
            LocaleDate::toStorageDateNearToday('1405/07/27'),
            $invoice->issue_date->toDateString(),
        );
    }

    public function test_confirmed_creates_purchase_draft(): void
    {
        [$user, $business] = $this->actingBusinessOwner();
        $party = Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'تأمین‌کننده سخت‌افزار',
            'is_customer' => false,
            'is_supplier' => true,
            'is_active' => true,
        ]);

        $result = (string) (new CreateInvoice)->handle(new Request([
            'confirmed' => true,
            'type' => 'purchase',
            'party_name' => 'تأمین‌کننده سخت‌افزار',
            'items' => [
                [
                    'title' => 'هارد',
                    'quantity' => '1',
                    'unit_price' => '۲۰۰۰۰۰۰',
                ],
            ],
        ]));

        $invoice = Invoice::query()->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceType::Purchase, $invoice->type);
        $this->assertSame($party->id, $invoice->party_id);
        $this->assertSame('2000000', bcadd((string) $invoice->total_amount, '0', 0));
        $this->assertStringContainsString(__('general.invoice_type_purchase'), $result);
        $this->assertSame($user->id, $invoice->created_by);
    }

    public function test_missing_party_asks_for_create_party(): void
    {
        $this->actingBusinessOwner();

        $result = (string) (new CreateInvoice)->handle(new Request([
            'confirmed' => false,
            'type' => 'sale',
            'party_name' => 'طرف‌حساب ناشناس',
            'items' => [
                [
                    'title' => 'کالا',
                    'quantity' => '1',
                    'unit_price' => '1000',
                ],
            ],
        ]));

        $this->assertStringContainsString('create_party', $result);
        $this->assertStringContainsString('طرف‌حساب ناشناس', $result);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_rejects_empty_items(): void
    {
        [$user, $business] = $this->actingBusinessOwner();
        $this->ensureParty($business->id, 'مدرسه حضرت زینب (س)');

        $result = (string) (new CreateInvoice)->handle(new Request([
            'confirmed' => true,
            'type' => 'sale',
            'party_name' => 'مدرسه حضرت زینب (س)',
            'items' => [],
        ]));

        $this->assertSame(__('general.ai_invoice_no_items'), $result);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame($user->id, auth()->id());
    }

    public function test_accounting_assistant_instructions_mention_create_invoice(): void
    {
        $this->actingBusinessOwner();

        $instructions = (string) (new AccountingAssistant)->instructions();

        $this->assertStringContainsString('create_invoice', $instructions);
        $this->assertStringContainsString('import_invoice', $instructions);
        $this->assertStringContainsString('متنِ فاکتور', $instructions);
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

    protected function ensureParty(int $businessId, string $name): Party
    {
        return Party::factory()->create([
            'business_id' => $businessId,
            'name' => $name,
            'is_customer' => true,
            'is_active' => true,
        ]);
    }
}
