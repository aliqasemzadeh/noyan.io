<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurrencyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_currency(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('currency.create')
            ->set('form.code', 'btc')
            ->set('form.name', 'Bitcoin')
            ->set('form.symbol', '₿')
            ->set('form.type', 'crypto')
            ->set('form.decimal_places', 8)
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('currencies', [
            'code' => 'BTC',
            'name' => 'Bitcoin',
            'decimal_places' => 8,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_currency(): void
    {
        $admin = User::factory()->create();
        $currency = Currency::factory()->create([
            'code' => 'ETH',
            'name' => 'Ether',
            'decimal_places' => 8,
        ]);

        Livewire::actingAs($admin)
            ->test('currency.edit')
            ->call('assignData', $currency)
            ->set('form.name', 'Ethereum')
            ->set('form.decimal_places', 18)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('currencies', [
            'id' => $currency->id,
            'name' => 'Ethereum',
            'decimal_places' => 18,
        ]);
    }

    public function test_admin_can_soft_delete_currency(): void
    {
        $admin = User::factory()->create();
        $currency = Currency::factory()->create(['code' => 'USDT']);

        Livewire::actingAs($admin)
            ->test('currency.delete')
            ->call('assignData', $currency)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted($currency);
    }

    public function test_currencies_index_page_is_accessible(): void
    {
        $admin = User::factory()->create();
        Currency::factory()->create(['code' => 'IRR', 'name' => 'Iranian Rial']);

        $this->actingAs($admin)
            ->get(route('system.currencies.index'))
            ->assertOk()
            ->assertSee('IRR');
    }

    public function test_cached_active_survives_database_cache_unserialize(): void
    {
        config(['cache.default' => 'database']);

        $currency = Currency::factory()->create([
            'code' => 'IRR',
            'name' => 'Iranian Rial',
            'is_system' => true,
            'business_id' => null,
            'is_active' => true,
        ]);

        Currency::forgetActiveCache();

        $first = Currency::cachedActive();
        $this->assertInstanceOf(Collection::class, $first);
        $this->assertTrue($first->contains('id', $currency->id));

        $second = Currency::cachedActive();
        $this->assertInstanceOf(Collection::class, $second);
        $this->assertTrue($second->first() instanceof Currency);
        $this->assertTrue($second->contains('id', $currency->id));
    }
}
