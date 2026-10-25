<?php

namespace Tests\Feature;

use App\Actions\Business\AcceptBusinessInvitationAction;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Business\BusinessPanel;
use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\Accounting\Invoice;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessUser;
use App\Models\User;
use App\Settings\InvoiceSystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
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

    public function test_seller_with_invoice_create_can_open_sale_and_purchase_create_pages(): void
    {
        [$seller] = $this->actingSeller([
            BusinessPermission::InvoiceView->value,
            BusinessPermission::InvoiceCreate->value,
            BusinessPermission::PartyView->value,
        ]);

        $this->actingAs($seller)
            ->get(route('sale.invoices.create', ['type' => 'sale']))
            ->assertOk()
            ->assertSee(__('general.new_sale_invoice'));

        $this->actingAs($seller)
            ->get(route('sale.invoices.create', ['type' => 'purchase']))
            ->assertOk()
            ->assertSee(__('general.new_purchase_invoice'));
    }

    public function test_sale_dashboard_shows_monthly_chart_and_create_buttons(): void
    {
        [$seller, $business] = $this->actingSeller([
            BusinessPermission::InvoiceView->value,
            BusinessPermission::InvoiceCreate->value,
            BusinessPermission::PartyView->value,
            BusinessPermission::QuoteView->value,
        ]);

        $now = Jalalian::now();
        $issueDate = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/15', $now->getYear(), $now->getMonth()))
            ->toCarbon()
            ->toDateString();

        Invoice::factory()->create([
            'business_id' => $business->id,
            'created_by' => $seller->id,
            'type' => InvoiceType::Sale,
            'issue_date' => $issueDate,
            'total_amount' => '1500000',
            'invoice_number' => 'SL-DASH-0001',
        ]);

        Invoice::factory()->purchase()->create([
            'business_id' => $business->id,
            'created_by' => $seller->id,
            'issue_date' => $issueDate,
            'total_amount' => '500000',
            'invoice_number' => 'PR-DASH-0001',
        ]);

        Cache::flush();

        $this->actingAs($seller)
            ->get(route('sale.dashboard'))
            ->assertOk()
            ->assertSee(__('general.monthly_sales_purchases_report'))
            ->assertSee(__('general.new_sale_invoice'))
            ->assertSee(__('general.new_purchase_invoice'));

        Livewire::actingAs($seller)
            ->test('pages::panel.sale.dashboard.index')
            ->assertSet('selectedYear', $now->getYear())
            ->assertSet('selectedMonth', $now->getMonth())
            ->assertSee(__('general.selected_month_summary'))
            ->assertSee('SL-DASH-0001')
            ->assertSee('PR-DASH-0001')
            ->call('selectMonth', $now->getMonth() === 1 ? 2 : 1)
            ->assertSet('selectedMonth', $now->getMonth() === 1 ? 2 : 1);
    }

    public function test_sale_dashboard_select_month_updates_breakdown(): void
    {
        [$seller, $business] = $this->actingSeller([
            BusinessPermission::InvoiceView->value,
            BusinessPermission::InvoiceCreate->value,
        ]);

        $year = Jalalian::now()->getYear();
        $farvardinDate = Jalalian::fromFormat('Y/m/d', sprintf('%04d/01/10', $year))
            ->toCarbon()
            ->toDateString();
        $ordibeheshtDate = Jalalian::fromFormat('Y/m/d', sprintf('%04d/02/10', $year))
            ->toCarbon()
            ->toDateString();

        Invoice::factory()->create([
            'business_id' => $business->id,
            'created_by' => $seller->id,
            'type' => InvoiceType::Sale,
            'issue_date' => $farvardinDate,
            'total_amount' => '1000000',
            'invoice_number' => 'SL-FAR-0001',
        ]);

        Invoice::factory()->purchase()->create([
            'business_id' => $business->id,
            'created_by' => $seller->id,
            'issue_date' => $ordibeheshtDate,
            'total_amount' => '250000',
            'invoice_number' => 'PR-ORD-0001',
        ]);

        Cache::flush();

        Livewire::actingAs($seller)
            ->test('pages::panel.sale.dashboard.index')
            ->set('selectedYear', $year)
            ->call('refreshChartData')
            ->call('selectMonth', 1)
            ->assertSet('selectedMonth', 1)
            ->assertSee('SL-FAR-0001')
            ->assertDontSee('PR-ORD-0001')
            ->call('selectMonth', 2)
            ->assertSet('selectedMonth', 2)
            ->assertSee('PR-ORD-0001')
            ->assertDontSee('SL-FAR-0001');
    }

    public function test_admin_can_invite_seller_with_custom_permissions(): void
    {
        Queue::fake();

        $admin = $this->grantAdministratorAccess(User::factory()->create());
        $owner = User::factory()->create();
        $member = User::factory()->create(['mobile' => '09123334455']);
        $business = Business::factory()->for($owner, 'owner')->create([
            'sms_wallet_balance' => '5000',
        ]);

        $settings = app(InvoiceSystemSettings::class);
        $settings->sms_unit_cost = '1000';
        $settings->save();

        $customPermissions = [
            BusinessPermission::QuoteView->value,
            BusinessPermission::InvoiceView->value,
            BusinessPermission::PartyView->value,
            BusinessPermission::PartyCreate->value,
        ];

        Livewire::actingAs($admin)
            ->test('business.create-user')
            ->call('assignData', $business)
            ->set('form.mobile', $member->mobile)
            ->set('form.roles', [BusinessRole::Seller->value])
            ->set('form.permissions', [
                BusinessRole::Seller->value => $customPermissions,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $invitation = BusinessInvitation::query()
            ->where('business_id', $business->id)
            ->where('mobile', $member->mobile)
            ->first();

        $this->assertNotNull($invitation);

        $membership = app(AcceptBusinessInvitationAction::class)
            ->handle($invitation, $member);

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
