<?php

namespace Tests\Feature;

use App\Enums\Accounting\InvoiceType;
use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Activity;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Catalog\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_business_activity_page(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $owner->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($owner)
            ->test('pages::panel.user.business.activity', ['business' => $business])
            ->assertOk()
            ->assertSee(__('general.business_activity_log'));
    }

    public function test_non_owner_member_cannot_open_business_activity_page(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        BusinessUser::factory()
            ->seller()
            ->for($business)
            ->for($member, 'user')
            ->create();

        $member->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($member)
            ->test('pages::panel.user.business.activity', ['business' => $business])
            ->assertForbidden();
    }

    public function test_party_changes_are_logged_with_business_id(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $owner->forceFill(['current_business_id' => $business->id])->save();

        $this->actingAs($owner);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'Initial Party',
        ]);

        $party->update(['name' => 'Updated Party']);

        $this->assertDatabaseHas('activity_log', [
            'business_id' => $business->id,
            'subject_type' => Party::class,
            'subject_id' => $party->id,
            'event' => 'updated',
        ]);

        $activity = Activity::query()
            ->forBusiness($business)
            ->where('subject_type', Party::class)
            ->where('subject_id', $party->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
    }

    public function test_activity_page_only_lists_current_business_rows(): void
    {
        $owner = User::factory()->create();
        $businessA = Business::factory()->for($owner, 'owner')->create(['name' => 'Biz A']);
        $businessB = Business::factory()->for($owner, 'owner')->create(['name' => 'Biz B']);
        $owner->forceFill(['current_business_id' => $businessA->id])->save();

        $this->actingAs($owner);

        Party::factory()->create(['business_id' => $businessA->id, 'name' => 'Party A']);
        Party::factory()->create(['business_id' => $businessB->id, 'name' => 'Party B']);

        $this->assertSame(1, Activity::query()->forBusiness($businessA)->where('subject_type', Party::class)->count());
        $this->assertSame(1, Activity::query()->forBusiness($businessB)->where('subject_type', Party::class)->count());

        $component = Livewire::actingAs($owner)
            ->test('pages::panel.user.business.activity', ['business' => $businessA])
            ->assertOk();

        $ids = collect($component->get('activities')->items())->pluck('business_id')->unique()->all();

        $this->assertSame([$businessA->id], $ids);
    }

    public function test_invoice_edit_finalized_permission_is_applicable_for_seller_and_accountant(): void
    {
        $this->assertContains(
            BusinessPermission::InvoiceEditFinalized,
            BusinessRole::Seller->applicablePermissions(),
        );
        $this->assertContains(
            BusinessPermission::InvoiceEditFinalized,
            BusinessRole::Accountant->applicablePermissions(),
        );
        $this->assertContains(
            BusinessPermission::InvoiceEditFinalized->value,
            BusinessRole::Seller->defaultPermissions(),
        );
        $this->assertContains(
            BusinessPermission::InvoiceEditFinalized->value,
            BusinessRole::Accountant->defaultPermissions(),
        );
    }

    public function test_owner_can_edit_finalized_invoice_and_activity_is_logged(): void
    {
        [$user, $business, $party, $product] = $this->seedSaleContext(stock: '10');

        $invoice = app(\App\Actions\Invoices\SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '2',
                    'unit_price' => '10000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ]);

        $invoice = app(\App\Actions\Invoices\FinalizeInvoiceAction::class)->handle($invoice, $user);

        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '8', 18));
        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '20000', 18));

        $updated = app(\App\Actions\Invoices\SaveInvoiceAction::class)->handle($user, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '3',
                    'unit_price' => '10000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ], $invoice);

        $this->assertNotNull($updated->finalized_at);
        $this->assertSame(0, bccomp((string) $updated->total_amount, '30000', 18));
        $this->assertSame(0, bccomp((string) $product->fresh()->stock_quantity, '7', 18));
        $this->assertSame(0, bccomp((string) $party->fresh()->balance, '30000', 18));

        $this->assertDatabaseHas('activity_log', [
            'business_id' => $business->id,
            'subject_type' => Invoice::class,
            'subject_id' => $updated->id,
            'event' => 'invoice_finalized_updated',
        ]);
    }

    public function test_member_without_permission_cannot_edit_finalized_invoice(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        BusinessUser::factory()
            ->for($business)
            ->for($member, 'user')
            ->withPermissions([
                BusinessPermission::InvoiceView->value,
                BusinessPermission::InvoiceEdit->value,
            ], BusinessRole::Seller)
            ->create();

        $member->forceFill(['current_business_id' => $business->id])->save();

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'is_customer' => true,
            'balance' => '0',
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'track_inventory' => true,
            'stock_quantity' => '10',
        ]);

        $owner->forceFill(['current_business_id' => $business->id])->save();

        $invoice = app(\App\Actions\Invoices\SaveInvoiceAction::class)->handle($owner, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '1',
                    'unit_price' => '5000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ]);

        $invoice = app(\App\Actions\Invoices\FinalizeInvoiceAction::class)->handle($invoice, $owner);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\App\Actions\Invoices\SaveInvoiceAction::class)->handle($member, [
            'type' => InvoiceType::Sale->value,
            'party_id' => $party->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'quantity' => '2',
                    'unit_price' => '5000',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                    'sort_order' => 0,
                ],
            ],
        ], $invoice);
    }

    /**
     * @return array{0: User, 1: Business, 2: Party, 3: Product}
     */
    protected function seedSaleContext(string $stock = '0'): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

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
}
