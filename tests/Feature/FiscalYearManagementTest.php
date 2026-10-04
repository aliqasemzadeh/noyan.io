<?php

namespace Tests\Feature;

use App\Models\Accounting\CostCenter;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\Project;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FiscalYearManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_fiscal_year(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $year = (int) now()->format('Y');

        Livewire::actingAs($user)
            ->test('accounting.fiscal-year.create')
            ->set('form.name', (string) $year)
            ->set('form.start_date', "{$year}-01-01")
            ->set('form.end_date', "{$year}-12-31")
            ->set('form.is_closed', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('fiscal_years', [
            'business_id' => $business->id,
            'name' => (string) $year,
            'is_closed' => false,
        ]);
    }

    public function test_user_can_create_cost_center_and_project(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        Livewire::actingAs($user)
            ->test('accounting.cost-center.create')
            ->set('form.code', 'CC-01')
            ->set('form.name', 'Operations')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($user)
            ->test('accounting.project.create')
            ->set('form.code', 'PR-01')
            ->set('form.name', 'Expansion')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cost_centers', [
            'business_id' => $business->id,
            'name' => 'Operations',
        ]);

        $this->assertDatabaseHas('projects', [
            'business_id' => $business->id,
            'name' => 'Expansion',
        ]);

        $this->assertInstanceOf(CostCenter::class, CostCenter::query()->first());
        $this->assertInstanceOf(Project::class, Project::query()->first());
        $this->assertInstanceOf(FiscalYear::class, FiscalYear::factory()->create([
            'business_id' => $business->id,
        ]));
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
