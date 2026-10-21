<?php

namespace Tests\Feature;

use App\Enums\Business\BusinessPanel;
use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class SalePanelAccessTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_seller_can_access_sale_panel_but_not_accounting(): void
    {
        [$seller] = $this->actingSeller();

        $this->actingAs($seller)
            ->get(route('sale.dashboard'))
            ->assertOk();

        $this->actingAs($seller)
            ->get(route('accounting.dashboard'))
            ->assertRedirect(route('sale.dashboard'));

        $this->assertTrue($seller->canAccessPanel(BusinessPanel::Sale));
        $this->assertFalse($seller->canAccessPanel(BusinessPanel::Accounting));
    }

    public function test_owner_can_access_both_panels(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $owner->forceFill(['current_business_id' => $business->id])->save();

        $this->actingAs($owner)
            ->get(route('accounting.dashboard'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('sale.dashboard'))
            ->assertOk();
    }

    public function test_seller_without_invoice_create_cannot_open_create_page(): void
    {
        [$seller] = $this->actingSeller([
            BusinessPermission::InvoiceView->value,
            BusinessPermission::PartyView->value,
            BusinessPermission::PartyCreate->value,
        ]);

        $this->assertTrue($seller->canInBusiness(BusinessPermission::InvoiceView));
        $this->assertFalse($seller->canInBusiness(BusinessPermission::InvoiceCreate));

        $this->actingAs($seller)
            ->get(route('sale.invoices.index'))
            ->assertOk();

        $this->actingAs($seller)
            ->get(route('sale.invoices.create', ['type' => 'sale']))
            ->assertForbidden();
    }

    public function test_admin_can_add_seller_with_custom_permissions(): void
    {
        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        $customPermissions = [
            BusinessPermission::QuoteView->value,
            BusinessPermission::InvoiceView->value,
            BusinessPermission::PartyView->value,
            BusinessPermission::PartyCreate->value,
        ];

        Livewire::actingAs($admin)
            ->test('business.create-user')
            ->call('assignData', $business)
            ->set('form.user_id', $member->id)
            ->set('form.roles', [BusinessRole::Seller->value])
            ->set('form.permissions', [
                BusinessRole::Seller->value => $customPermissions,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $membership = BusinessUser::query()
            ->where('business_id', $business->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertTrue($membership->hasRole(BusinessRole::Seller));
        $this->assertSame($customPermissions, $membership->permissionsFor(BusinessRole::Seller));
        $this->assertFalse($membership->hasPermission(BusinessPermission::InvoiceCreate));
    }

    /**
     * @param  list<string>|null  $permissions
     * @return array{0: User, 1: Business}
     */
    protected function actingSeller(?array $permissions = null): array
    {
        $owner = User::factory()->create();
        $seller = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        $factory = BusinessUser::factory()
            ->seller()
            ->for($business)
            ->for($seller, 'user');

        if ($permissions !== null) {
            $factory = $factory->withPermissions($permissions, BusinessRole::Seller);
        }

        $factory->create();

        $seller->forceFill(['current_business_id' => $business->id])->save();

        return [$seller->fresh(), $business];
    }
}
