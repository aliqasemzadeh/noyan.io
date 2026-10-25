<?php

namespace Tests\Feature;

use App\Models\Accounting\SaleKind;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\SaleKindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaleKindManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_sale_kinds_are_seeded(): void
    {
        $this->seed(SaleKindSeeder::class);

        $this->assertDatabaseHas('sale_kinds', [
            'code' => 'formal',
            'is_system' => true,
            'business_id' => null,
        ]);

        $this->assertSame(2, SaleKind::query()->system()->count());
    }

    public function test_user_can_activate_system_sale_kind_for_business(): void
    {
        $this->seed(SaleKindSeeder::class);

        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $saleKind = SaleKind::query()->system()->where('code', 'formal')->firstOrFail();

        Livewire::actingAs($user)
            ->test('accounting.sale-kind.activate')
            ->set('form.sale_kind_id', $saleKind->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_sale_kinds', [
            'business_id' => $business->id,
            'sale_kind_id' => $saleKind->id,
            'is_default' => true,
        ]);
    }

    public function test_user_can_create_custom_sale_kind(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($user)
            ->test('accounting.sale-kind.create-custom')
            ->set('form.name', 'قسطی ویژه')
            ->set('form.code', 'special-installment')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sale_kinds', [
            'code' => 'special-installment',
            'name' => 'قسطی ویژه',
            'business_id' => $business->id,
            'is_system' => false,
        ]);

        $saleKind = SaleKind::query()->where('code', 'special-installment')->first();
        $this->assertNotNull($saleKind);

        $this->assertDatabaseHas('business_sale_kinds', [
            'business_id' => $business->id,
            'sale_kind_id' => $saleKind->id,
        ]);
    }

    public function test_sale_kinds_index_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $this->actingAs($user)
            ->get(route('accounting.sale-kinds.index'))
            ->assertOk()
            ->assertSee(__('general.sale_kinds'));
    }
}
