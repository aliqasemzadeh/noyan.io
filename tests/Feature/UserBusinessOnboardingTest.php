<?php

namespace Tests\Feature;

use App\Enums\AccountSubType;
use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessRole;
use App\Enums\Business\BusinessType;
use App\Enums\CurrencyType;
use App\Models\Accounting\Account;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserBusinessOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function seedIrtCurrency(): Currency
    {
        return Currency::factory()->create([
            'code' => 'IRT',
            'name' => 'Iranian Toman',
            'symbol' => 'ت',
            'type' => CurrencyType::Fiat,
            'is_system' => true,
            'business_id' => null,
            'decimal_places' => 0,
            'is_active' => true,
        ]);
    }

    public function test_user_without_business_is_redirected_from_dashboard_to_create(): void
    {
        $user = User::factory()->create(['current_business_id' => null]);

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('user.businesses.create'));
    }

    public function test_user_can_create_business_with_auto_provisioning(): void
    {
        $user = User::factory()->create(['current_business_id' => null]);
        $currency = $this->seedIrtCurrency();

        Livewire::actingAs($user)
            ->test('pages::panel.user.business.create')
            ->assertOk()
            ->set('form.name', 'فروشگاه نوید')
            ->set('form.currency_ids', [$currency->id])
            ->set('form.type', BusinessType::Store->value)
            ->set('form.category', BusinessCategory::Computers->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('accounting.accounts.index'));

        $business = Business::query()->where('name', 'فروشگاه نوید')->first();

        $this->assertNotNull($business);
        $this->assertSame(BusinessType::Store, $business->type);
        $this->assertSame(BusinessCategory::Computers, $business->category);
        $this->assertSame('store', $business->getRawOriginal('type'));
        $this->assertSame('computers', $business->getRawOriginal('category'));
        $this->assertSame($user->id, $business->owner_id);
        $this->assertSame($business->id, $user->fresh()->current_business_id);

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => BusinessRole::Owner->value,
        ]);

        $this->assertDatabaseHas('business_currencies', [
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'is_base' => true,
        ]);

        $this->assertSame(2, Account::query()->where('business_id', $business->id)->count());
        $this->assertDatabaseHas('accounting_accounts', [
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'sub_type' => AccountSubType::Cash->value,
        ]);
        $this->assertDatabaseHas('accounting_accounts', [
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'sub_type' => AccountSubType::Bank->value,
        ]);
    }

    public function test_user_can_create_business_with_multiple_currencies(): void
    {
        $user = User::factory()->create(['current_business_id' => null]);
        $base = $this->seedIrtCurrency();
        $usd = Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'type' => CurrencyType::Fiat,
            'is_system' => true,
            'business_id' => null,
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.user.business.create')
            ->set('form.name', 'Multi Currency Biz')
            ->set('form.currency_ids', [$base->id, $usd->id])
            ->set('form.type', BusinessType::Store->value)
            ->set('form.category', BusinessCategory::Other->value)
            ->call('save')
            ->assertHasNoErrors();

        $business = Business::query()->where('name', 'Multi Currency Biz')->first();

        $this->assertNotNull($business);

        $this->assertDatabaseHas('business_currencies', [
            'business_id' => $business->id,
            'currency_id' => $base->id,
            'is_base' => true,
        ]);
        $this->assertDatabaseHas('business_currencies', [
            'business_id' => $business->id,
            'currency_id' => $usd->id,
            'is_base' => false,
        ]);

        $this->assertSame(2, Account::query()->where('business_id', $business->id)->count());
        $this->assertSame(0, Account::query()
            ->where('business_id', $business->id)
            ->where('currency_id', $usd->id)
            ->count());
    }

    public function test_create_business_requires_at_least_one_currency(): void
    {
        $user = User::factory()->create(['current_business_id' => null]);
        $this->seedIrtCurrency();

        Livewire::actingAs($user)
            ->test('pages::panel.user.business.create')
            ->set('form.name', 'No Currency Biz')
            ->set('form.currency_ids', [])
            ->set('form.type', BusinessType::Store->value)
            ->set('form.category', BusinessCategory::Other->value)
            ->call('save')
            ->assertHasErrors(['form.currency_ids']);
    }

    public function test_create_business_validates_enum_values(): void
    {
        $user = User::factory()->create();
        $currency = $this->seedIrtCurrency();

        Livewire::actingAs($user)
            ->test('pages::panel.user.business.create')
            ->set('form.name', 'Test Biz')
            ->set('form.currency_ids', [$currency->id])
            ->set('form.type', 'not-a-type')
            ->set('form.category', 'not-a-category')
            ->call('save')
            ->assertHasErrors(['form.type', 'form.category']);
    }

    public function test_user_can_create_second_business_and_switch_current(): void
    {
        $user = User::factory()->create();
        $first = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $first->id])->save();
        $currency = $this->seedIrtCurrency();

        Livewire::actingAs($user)
            ->test('pages::panel.user.business.create')
            ->set('form.name', 'Second Business')
            ->set('form.currency_ids', [$currency->id])
            ->set('form.type', BusinessType::Service->value)
            ->set('form.category', BusinessCategory::Freelance->value)
            ->call('save')
            ->assertHasNoErrors();

        $second = Business::query()->where('name', 'Second Business')->first();

        $this->assertNotNull($second);
        $this->assertSame($second->id, $user->fresh()->current_business_id);
        $this->assertSame(2, $user->businesses()->count());
    }

    public function test_user_can_edit_own_business(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'name' => 'Old Name',
            'type' => BusinessType::Store,
            'category' => BusinessCategory::Other,
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($user)
            ->test('pages::panel.user.business.edit', ['business' => $business])
            ->set('form.name', 'New Name')
            ->set('form.type', BusinessType::Service->value)
            ->set('form.category', BusinessCategory::Education->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('user.businesses.view', $business));

        $business->refresh();

        $this->assertSame('New Name', $business->name);
        $this->assertSame(BusinessType::Service, $business->type);
        $this->assertSame(BusinessCategory::Education, $business->category);
    }

    public function test_user_can_update_optional_invoice_branding_fields(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'type' => BusinessType::Store,
            'category' => BusinessCategory::Other,
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($user)
            ->test('pages::panel.user.business.edit', ['business' => $business])
            ->set('form.name', $business->name)
            ->set('form.type', BusinessType::Store->value)
            ->set('form.category', BusinessCategory::Other->value)
            ->set('form.phone', '02112345678')
            ->set('form.address', 'Tehran')
            ->set('form.invoice_primary_color', '#0ea5e9')
            ->set('form.invoice_secondary_color', '#0369a1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('user.businesses.view', $business));

        $business->refresh();

        $this->assertSame('02112345678', $business->phone);
        $this->assertSame('Tehran', $business->address);
        $this->assertSame('#0ea5e9', $business->invoice_primary_color);
        $this->assertSame('#0369a1', $business->invoice_secondary_color);
    }

    public function test_foreign_user_cannot_edit_business(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $stranger->forceFill(['current_business_id' => null])->save();

        // Give stranger a business so middleware allows access to edit route group
        $own = Business::factory()->for($stranger, 'owner')->create();
        $stranger->forceFill(['current_business_id' => $own->id])->save();

        Livewire::actingAs($stranger)
            ->test('pages::panel.user.business.edit', ['business' => $business])
            ->assertForbidden();
    }

    public function test_business_type_label_falls_back_to_case_name(): void
    {
        app()->setLocale('en');
        $this->assertSame('Store / Trading', BusinessType::Store->label());

        app('translator')->addLines([
            'business_types.personal' => 'business_types.personal',
            'business_categories.home_appliances' => 'business_categories.home_appliances',
        ], 'en');

        $this->assertSame('Personal', BusinessType::Personal->label());
        $this->assertSame('HomeAppliances', BusinessCategory::HomeAppliances->label());
    }

    public function test_user_with_membership_but_null_current_gets_auto_selected(): void
    {
        $user = User::factory()->create(['current_business_id' => null]);
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => null])->save();

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertOk();

        $this->assertSame($business->id, $user->fresh()->current_business_id);
    }

    public function test_create_page_is_accessible_without_current_business(): void
    {
        $user = User::factory()->create(['current_business_id' => null]);
        $this->seedIrtCurrency();

        $this->actingAs($user)
            ->get(route('user.businesses.create'))
            ->assertOk();
    }
}
