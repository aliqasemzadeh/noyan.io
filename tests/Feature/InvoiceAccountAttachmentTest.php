<?php

namespace Tests\Feature;

use App\Actions\Invoices\FinalizeInvoiceAction;
use App\Actions\Invoices\SaveInvoiceAction;
use App\Enums\Accounting\InvoiceType;
use App\Enums\AccountSubType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceAccountAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_form_can_attach_optional_accounts(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $currency = Currency::factory()->create();
        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_customer' => true,
        ]);
        $account = Account::factory()->bank()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'Main Bank',
            'sub_type' => AccountSubType::Bank,
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.invoice.form', ['type' => 'sale'])
            ->set('form.party_id', $party->id)
            ->set('form.party_name', $party->name)
            ->set('form.issue_date', now()->toDateString())
            ->set('form.items.0.title', 'Service')
            ->set('form.items.0.quantity', '1')
            ->set('form.items.0.unit_price', '1000')
            ->set('form.account_ids', [$account->id])
            ->call('saveAsDraft')
            ->assertHasNoErrors()
            ->assertRedirect();

        $invoice = Invoice::query()->where('business_id', $business->id)->first();

        $this->assertNotNull($invoice);
        $this->assertTrue($invoice->accounts->contains('id', $account->id));
    }

    public function test_public_invoice_shows_attached_accounts(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $currency = Currency::factory()->create();
        $account = Account::factory()->bank()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'Settlement Account',
            'iban' => 'IR062960000000100324200001',
        ]);

        $invoice = Invoice::factory()->finalized()->create([
            'business_id' => $business->id,
            'created_by' => $user->id,
            'party_name' => 'Customer',
            'items_total' => '1000',
            'total_amount' => '1000',
            'type' => InvoiceType::Sale,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => '1',
            'unit_price' => '1000',
            'total' => '1000',
        ]);

        $invoice->accounts()->sync([$account->id]);

        $url = URL::signedRoute('invoices.public', ['invoice' => $invoice]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Settlement Account')
            ->assertSee('IR062960000000100324200001')
            ->assertSee(__('general.invoice_payment_accounts'));
    }

    public function test_save_invoice_action_syncs_accounts(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create();
        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_customer' => true,
        ]);
        $first = Account::factory()->bank()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
        ]);
        $second = Account::factory()->bank()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
        ]);

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'account_ids' => [$first->id, $second->id],
            'items' => [
                [
                    'title' => 'Item',
                    'quantity' => '1',
                    'unit_price' => '100',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                ],
            ],
        ]);

        $this->assertCount(2, $invoice->accounts);

        $invoice = app(SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'account_ids' => [$first->id],
            'items' => [
                [
                    'title' => 'Item',
                    'quantity' => '1',
                    'unit_price' => '100',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                ],
            ],
        ], $invoice);

        $this->assertCount(1, $invoice->fresh()->accounts);
        $this->assertTrue($invoice->fresh()->accounts->contains('id', $first->id));

        app(FinalizeInvoiceAction::class)->handle($invoice->fresh(), $user);

        $this->assertTrue($invoice->fresh()->isFinalized());
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
