<?php

namespace Tests\Feature;

use App\Ai\Tools\CreateParty;
use App\Enums\PartyType;
use App\Models\Accounting\Party;
use App\Models\Accounting\PartyContact;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AccountingAiCreatePartyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_party_registers_customer(): void
    {
        [$user, $business] = $this->actingBusinessOwner();

        $result = (string) (new CreateParty)->handle(new Request([
            'name' => 'مدرسه حضرت زینب (س)',
            'mobile' => '09121234567',
        ]));

        $this->assertSame(__('general.ai_party_created', [
            'name' => 'مدرسه حضرت زینب (س)',
        ]), $result);

        $this->assertDatabaseHas('parties', [
            'business_id' => $business->id,
            'user_id' => $user->id,
            'name' => 'مدرسه حضرت زینب (س)',
            'type' => PartyType::Individual->value,
            'mobile' => '09121234567',
            'is_customer' => true,
            'is_supplier' => false,
        ]);
    }

    public function test_create_party_rejects_duplicate_name(): void
    {
        [, $business] = $this->actingBusinessOwner();

        Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'هنرستان طالقانی',
            'is_customer' => true,
        ]);

        $result = (string) (new CreateParty)->handle(new Request([
            'name' => 'هنرستان طالقانی',
        ]));

        $this->assertSame(__('general.ai_party_already_exists', [
            'name' => 'هنرستان طالقانی',
        ]), $result);
        $this->assertDatabaseCount('parties', 1);
    }

    public function test_create_party_rejects_duplicate_legal_name_match(): void
    {
        [, $business] = $this->actingBusinessOwner();

        Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'مخفف',
            'legal_name' => 'آموزش و پرورش ناحیه 2',
            'is_customer' => true,
        ]);

        $result = (string) (new CreateParty)->handle(new Request([
            'name' => 'آموزش و پرورش ناحیه 2',
        ]));

        $this->assertSame(__('general.ai_party_already_exists', [
            'name' => 'آموزش و پرورش ناحیه 2',
        ]), $result);
        $this->assertDatabaseCount('parties', 1);
    }

    public function test_create_party_can_attach_primary_contact(): void
    {
        [, $business] = $this->actingBusinessOwner();

        $result = (string) (new CreateParty)->handle(new Request([
            'name' => 'شاهد علامه امینی',
            'contact_name' => 'مدیر خرید',
            'contact_mobile' => '09123334455',
        ]));

        $this->assertStringContainsString('شاهد علامه امینی', $result);

        $party = Party::query()->where('business_id', $business->id)->first();
        $this->assertNotNull($party);

        $this->assertDatabaseHas('party_contacts', [
            'party_id' => $party->id,
            'name' => 'مدیر خرید',
            'mobile' => '09123334455',
            'is_primary' => true,
        ]);
        $this->assertSame(1, PartyContact::query()->count());
    }

    public function test_create_party_requires_business(): void
    {
        $user = User::factory()->create(['current_business_id' => null]);
        $this->actingAs($user);

        $result = (string) (new CreateParty)->handle(new Request([
            'name' => 'بدون کسب‌وکار',
        ]));

        $this->assertSame(__('general.business_required'), $result);
        $this->assertDatabaseCount('parties', 0);
    }

    /**
     * @return array{0: User, 1: Business}
     */
    protected function actingBusinessOwner(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'name' => 'فروشگاه تست',
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();
        $this->actingAs($user);

        return [$user, $business];
    }
}
