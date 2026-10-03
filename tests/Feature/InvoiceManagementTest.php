<?php

namespace Tests\Feature;

use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Business;
use App\Models\Catalog\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_invoice_form_page_loads(): void
    {
        [$user] = $this->actingBusinessUser();

        $this->actingAs($user)
            ->get(route('accounting.invoices.create', ['type' => 'sale']))
            ->assertOk()
            ->assertSee(__('general.new_sale_invoice'));
    }

    public function test_purchase_invoice_form_page_loads(): void
    {
        [$user] = $this->actingBusinessUser();

        $this->actingAs($user)
            ->get(route('accounting.invoices.create', ['type' => 'purchase']))
            ->assertOk()
            ->assertSee(__('general.new_purchase_invoice'));
    }

    public function test_user_can_save_invoice_draft_from_form(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_customer' => true,
            'name' => 'Customer One',
        ]);
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Laptop',
            'sale_price' => '25000000',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.invoice.form', ['type' => 'sale'])
            ->set('form.party_id', $party->id)
            ->set('form.party_name', $party->name)
            ->set('form.issue_date', now()->toDateString())
            ->set('form.items.0.product_id', $product->id)
            ->set('form.items.0.title', $product->name)
            ->set('form.items.0.quantity', '1')
            ->set('form.items.0.unit_price', '25000000')
            ->call('saveAsDraft')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'business_id' => $business->id,
            'party_id' => $party->id,
            'type' => InvoiceType::Sale->value,
            'finalized_at' => null,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'title' => 'Laptop',
            'product_id' => $product->id,
        ]);
    }

    public function test_invoices_index_lists_current_business_only(): void
    {
        $user = User::factory()->create();
        $current = Business::factory()->for($user, 'owner')->create();
        $other = Business::factory()->create();
        $user->forceFill(['current_business_id' => $current->id])->save();

        Invoice::factory()->create([
            'business_id' => $current->id,
            'created_by' => $user->id,
            'invoice_number' => 'SL-1405-01-0001',
            'party_name' => 'Visible',
        ]);

        Invoice::factory()->create([
            'business_id' => $other->id,
            'created_by' => User::factory(),
            'invoice_number' => 'SL-1405-01-0099',
            'party_name' => 'Hidden',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.invoice.index')
            ->assertSee('SL-1405-01-0001')
            ->assertDontSee('SL-1405-01-0099');
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
