<?php

namespace Tests\Feature;

use App\Enums\Accounting\DetailType;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\GlDetail;
use App\Models\Accounting\Party;
use App\Models\Accounting\Project;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlDetailSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_party_account_cost_center_and_project_creates_gl_details(): void
    {
        [$user, $business] = $this->actingBusinessUser();
        $currency = Currency::factory()->create(['business_id' => null, 'is_system' => true]);

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'Acme Co',
            'is_active' => true,
        ]);

        $account = Account::factory()->create([
            'business_id' => $business->id,
            'currency_id' => $currency->id,
            'name' => 'Main bank',
            'is_active' => true,
        ]);

        $costCenter = CostCenter::factory()->create([
            'business_id' => $business->id,
            'code' => 'CC1',
            'name' => 'Ops',
        ]);

        $project = Project::factory()->create([
            'business_id' => $business->id,
            'code' => 'PR1',
            'name' => 'Expansion',
        ]);

        $this->assertDatabaseHas('gl_details', [
            'business_id' => $business->id,
            'type' => DetailType::Party->value,
            'detailable_id' => $party->id,
            'name' => 'Acme Co',
        ]);

        $this->assertDatabaseHas('gl_details', [
            'business_id' => $business->id,
            'type' => DetailType::Account->value,
            'detailable_id' => $account->id,
            'name' => 'Main bank',
        ]);

        $this->assertDatabaseHas('gl_details', [
            'business_id' => $business->id,
            'type' => DetailType::CostCenter->value,
            'detailable_id' => $costCenter->id,
        ]);

        $this->assertDatabaseHas('gl_details', [
            'business_id' => $business->id,
            'type' => DetailType::Project->value,
            'detailable_id' => $project->id,
        ]);

        $this->assertSame($user->id, $business->owner_id);
        $this->assertInstanceOf(GlDetail::class, $party->glDetail);
    }

    public function test_soft_deleting_party_soft_deletes_gl_detail(): void
    {
        [, $business] = $this->actingBusinessUser();

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'To Delete',
        ]);

        $detailId = $party->glDetail?->id;
        $this->assertNotNull($detailId);

        $party->delete();

        $this->assertSoftDeleted('gl_details', ['id' => $detailId]);
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
