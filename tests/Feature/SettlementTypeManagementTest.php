<?php

namespace Tests\Feature;

use App\Models\Accounting\SettlementType;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\SettlementTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettlementTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_settlement_types_are_seeded(): void
    {
        $this->seed(SettlementTypeSeeder::class);

        $this->assertDatabaseHas('settlement_types', [
            'code' => 'cash',
            'is_system' => true,
            'business_id' => null,
        ]);

        $this->assertSame(4, SettlementType::query()->system()->count());
    }

    public function test_user_can_activate_system_settlement_type_for_business(): void
    {
        $this->seed(SettlementTypeSeeder::class);

        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $settlementType = SettlementType::query()->system()->where('code', 'cash')->firstOrFail();

        Livewire::actingAs($user)
            ->test('accounting.settlement-type.activate')
            ->set('form.settlement_type_id', $settlementType->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_settlement_types', [
            'business_id' => $business->id,
            'settlement_type_id' => $settlementType->id,
            'is_default' => true,
        ]);
    }

    public function test_user_can_create_custom_settlement_type(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($user)
            ->test('accounting.settlement-type.create-custom')
            ->set('form.name', 'قسطی ویژه')
            ->set('form.code', 'special-installment')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('settlement_types', [
            'code' => 'special-installment',
            'name' => 'قسطی ویژه',
            'business_id' => $business->id,
            'is_system' => false,
        ]);

        $settlementType = SettlementType::query()->where('code', 'special-installment')->first();
        $this->assertNotNull($settlementType);

        $this->assertDatabaseHas('business_settlement_types', [
            'business_id' => $business->id,
            'settlement_type_id' => $settlementType->id,
        ]);
    }

    public function test_settlement_types_index_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        $this->actingAs($user)
            ->get(route('accounting.settlement-types.index'))
            ->assertOk()
            ->assertSee(__('general.settlement_types'));
    }
}
