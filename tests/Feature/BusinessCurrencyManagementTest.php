<?php

namespace Tests\Feature;

use App\Enums\CurrencyType;
use App\Models\Accounting\BusinessCurrency;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessCurrencyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_activate_system_currency_for_business(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create(['code' => 'USD', 'decimal_places' => 2]);

        Livewire::actingAs($user)
            ->test('accounting.currency.activate')
            ->set('form.currency_id', $currency->id)
            ->set('form.exchange_rate_to_base', '60000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_currencies', [
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'is_base' => true,
            'exchange_rate_to_base' => 1,
        ]);
    }

    public function test_first_activated_currency_becomes_base_automatically(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create(['code' => 'IRT', 'decimal_places' => 0]);

        $business->activateCurrency($currency, '1');

        $this->assertDatabaseHas('business_currencies', [
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'is_base' => true,
        ]);
    }

    public function test_user_can_set_base_currency(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $irt = Currency::factory()->create(['code' => 'IRT', 'decimal_places' => 0]);
        $usd = Currency::factory()->create(['code' => 'USD', 'decimal_places' => 2]);

        $base = $business->activateCurrency($irt, '1');
        $secondary = $business->activateCurrency($usd, '60000');

        Livewire::actingAs($user)
            ->test('accounting.currency.edit')
            ->call('assignData', $secondary)
            ->set('form.is_base', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_currencies', [
            'id' => $secondary->id,
            'is_base' => true,
            'exchange_rate_to_base' => 1,
        ]);

        $this->assertDatabaseHas('business_currencies', [
            'id' => $base->id,
            'is_base' => false,
        ]);
    }

    public function test_user_can_create_custom_asset_for_business(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($user)
            ->test('accounting.currency.create-custom')
            ->set('form.code', 'local_token')
            ->set('form.name', 'Local Token')
            ->set('form.symbol', 'LT')
            ->set('form.decimal_places', 4)
            ->set('form.exchange_rate_to_base', '100')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('currencies', [
            'code' => 'LOCAL_TOKEN',
            'business_id' => $business->id,
            'type' => CurrencyType::Custom->value,
            'is_system' => false,
        ]);

        $currency = Currency::query()->where('code', 'LOCAL_TOKEN')->first();
        $this->assertNotNull($currency);

        $this->assertDatabaseHas('business_currencies', [
            'business_id' => $business->id,
            'currency_id' => $currency->id,
        ]);
    }

    public function test_business_currencies_index_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();
        $currency = Currency::factory()->create(['code' => 'BTC']);
        $business->activateCurrency($currency, '1');

        $this->actingAs($user)
            ->get(route('accounting.currencies.index'))
            ->assertOk()
            ->assertSee('BTC');
    }

    public function test_cached_for_business_survives_database_cache_unserialize(): void
    {
        config(['cache.default' => 'database']);

        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $currency = Currency::factory()->create(['code' => 'USD']);
        $business->activateCurrency($currency, '1');

        BusinessCurrency::forgetCache($business->id);

        $first = Currency::cachedForBusiness($business->id);
        $this->assertInstanceOf(Collection::class, $first);
        $this->assertTrue($first->contains('id', $currency->id));

        $second = Currency::cachedForBusiness($business->id);
        $this->assertInstanceOf(Collection::class, $second);
        $this->assertTrue($second->first() instanceof Currency);
        $this->assertTrue($second->contains('id', $currency->id));
        $this->assertTrue($second->first()->relationLoaded('businessCurrencies'));
    }
}
