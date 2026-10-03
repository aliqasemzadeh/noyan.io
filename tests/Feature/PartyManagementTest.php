<?php

namespace Tests\Feature;

use App\Enums\PartyType;
use App\Models\Accounting\Party;
use App\Models\Accounting\PartyContact;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_individual_party(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        Livewire::actingAs($user)
            ->test('accounting.party.create')
            ->set('form.type', PartyType::Individual->value)
            ->set('form.name', 'Ali Rezaei')
            ->set('form.mobile', '09121234567')
            ->set('form.is_customer', true)
            ->set('form.is_supplier', false)
            ->set('form.credit_limit', '1000000')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('parties', [
            'business_id' => $business->id,
            'name' => 'Ali Rezaei',
            'type' => PartyType::Individual->value,
            'mobile' => '09121234567',
            'is_customer' => true,
            'is_supplier' => false,
            'balance' => 0,
        ]);
    }

    public function test_user_can_create_company_party(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        Livewire::actingAs($user)
            ->test('accounting.party.create')
            ->set('form.type', PartyType::Company->value)
            ->set('form.name', 'Drak Co')
            ->set('form.legal_name', 'Drak Trading Company')
            ->set('form.is_customer', true)
            ->set('form.is_supplier', true)
            ->set('form.credit_limit', '0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('parties', [
            'business_id' => $business->id,
            'name' => 'Drak Co',
            'legal_name' => 'Drak Trading Company',
            'type' => PartyType::Company->value,
            'is_customer' => true,
            'is_supplier' => true,
        ]);
    }

    public function test_party_requires_customer_or_supplier_role(): void
    {
        [$user] = $this->actingBusinessUser();

        Livewire::actingAs($user)
            ->test('accounting.party.create')
            ->set('form.name', 'No Role Party')
            ->set('form.is_customer', false)
            ->set('form.is_supplier', false)
            ->call('save')
            ->assertHasErrors(['form.is_customer']);
    }

    public function test_parties_are_scoped_to_current_business(): void
    {
        $user = User::factory()->create();
        $current = Business::factory()->for($user, 'owner')->create(['name' => 'Current']);
        $other = Business::factory()->create(['name' => 'Other']);
        $user->forceFill(['current_business_id' => $current->id])->save();

        Party::factory()->create([
            'business_id' => $current->id,
            'name' => 'Visible Party',
        ]);

        Party::factory()->create([
            'business_id' => $other->id,
            'name' => 'Hidden Party',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.party.index')
            ->assertSee('Visible Party')
            ->assertDontSee('Hidden Party');
    }

    public function test_user_can_view_party_and_manage_contacts(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $party = Party::factory()->company()->create([
            'business_id' => $business->id,
            'name' => 'Drak Co',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.party.view', ['party' => $party])
            ->assertSee('Drak Co')
            ->assertSee(__('general.party_contacts'));

        Livewire::actingAs($user)
            ->test('accounting.party.contact.create')
            ->call('assignData', $party)
            ->set('form.name', 'Purchasing Manager')
            ->set('form.position', 'مدیر خرید')
            ->set('form.mobile', '09121234567')
            ->set('form.is_primary', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('party_contacts', [
            'party_id' => $party->id,
            'name' => 'Purchasing Manager',
            'position' => 'مدیر خرید',
            'is_primary' => true,
        ]);

        $contact = PartyContact::query()->where('party_id', $party->id)->firstOrFail();

        Livewire::actingAs($user)
            ->test('accounting.party.contact.edit')
            ->call('assignData', $contact)
            ->set('form.name', 'Head of Purchasing')
            ->set('form.position', 'مدیر ارشد خرید')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('party_contacts', [
            'id' => $contact->id,
            'name' => 'Head of Purchasing',
            'position' => 'مدیر ارشد خرید',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.party.contact.delete')
            ->call('assignData', $contact->fresh())
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('party_contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_user_cannot_view_party_from_another_business(): void
    {
        [$user] = $this->actingBusinessUser();
        $other = Business::factory()->create();

        $party = Party::factory()->create([
            'business_id' => $other->id,
            'name' => 'Foreign Party',
        ]);

        Livewire::actingAs($user)
            ->test('pages::panel.accounting.party.view', ['party' => $party])
            ->assertForbidden();
    }

    public function test_user_can_update_and_soft_delete_party_with_contacts(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $party = Party::factory()->create([
            'business_id' => $business->id,
            'name' => 'Old Name',
        ]);

        $contact = PartyContact::factory()->create([
            'party_id' => $party->id,
            'name' => 'Accountant',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.party.edit')
            ->call('assignData', $party)
            ->set('form.name', 'New Name')
            ->set('form.is_supplier', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('parties', [
            'id' => $party->id,
            'name' => 'New Name',
            'is_supplier' => true,
        ]);

        Livewire::actingAs($user)
            ->test('accounting.party.delete')
            ->call('assignData', $party->fresh())
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('parties', [
            'id' => $party->id,
        ]);

        $this->assertSoftDeleted('party_contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_setting_primary_contact_clears_previous_primary(): void
    {
        [$user, $business] = $this->actingBusinessUser();

        $party = Party::factory()->create([
            'business_id' => $business->id,
        ]);

        $first = PartyContact::factory()->primary()->create([
            'party_id' => $party->id,
            'name' => 'First Contact',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.party.contact.create')
            ->call('assignData', $party)
            ->set('form.name', 'Second Contact')
            ->set('form.is_primary', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue(
            PartyContact::query()
                ->where('party_id', $party->id)
                ->where('name', 'Second Contact')
                ->where('is_primary', true)
                ->exists()
        );
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
