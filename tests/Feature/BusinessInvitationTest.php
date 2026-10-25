<?php

namespace Tests\Feature;

use App\Actions\Business\AcceptBusinessInvitationAction;
use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Jobs\Notification\User\SendBusinessInvitationJob;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessUser;
use App\Models\User;
use App\Settings\InvoiceSystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_invites_non_member_by_mobile_instead_of_listing_users(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create([
            'sms_wallet_balance' => '5000',
        ]);
        $owner->forceFill(['current_business_id' => $business->id])->save();

        $settings = app(InvoiceSystemSettings::class);
        $settings->sms_unit_cost = '1000';
        $settings->save();

        $outsider = User::factory()->create(['mobile' => '09121234567']);

        Livewire::actingAs($owner)
            ->test('business.create-user')
            ->call('assignData', $business)
            ->assertDontSee($outsider->mobile)
            ->set('form.mobile', '09129876543')
            ->set('form.roles', [BusinessRole::Seller->value])
            ->set('form.permissions', [
                BusinessRole::Seller->value => [
                    BusinessPermission::InvoiceView->value,
                    BusinessPermission::PartyView->value,
                ],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('panels.user.business.users.table');

        $invitation = BusinessInvitation::query()
            ->where('business_id', $business->id)
            ->where('mobile', '09129876543')
            ->first();

        $this->assertNotNull($invitation);
        $this->assertTrue($invitation->isPending());
        $this->assertDatabaseMissing('business_user', [
            'business_id' => $business->id,
            'user_id' => $outsider->id,
        ]);

        Queue::assertPushed(SendBusinessInvitationJob::class);
        $this->assertSame('4000.00', (string) $business->fresh()->sms_wallet_balance);
    }

    public function test_owner_updates_existing_member_permissions_by_mobile(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['mobile' => '09121112233']);
        $business = Business::factory()->for($owner, 'owner')->create([
            'sms_wallet_balance' => '5000',
        ]);
        $owner->forceFill(['current_business_id' => $business->id])->save();

        $membership = BusinessUser::factory()
            ->seller()
            ->for($business)
            ->for($member, 'user')
            ->create();

        $updatedPermissions = [
            BusinessPermission::QuoteView->value,
            BusinessPermission::InvoiceView->value,
            BusinessPermission::PartyView->value,
        ];

        Livewire::actingAs($owner)
            ->test('business.create-user')
            ->call('assignData', $business)
            ->set('form.mobile', '09121112233')
            ->set('form.roles', [BusinessRole::Seller->value])
            ->set('form.permissions', [
                BusinessRole::Seller->value => $updatedPermissions,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $membership->refresh();

        $this->assertSame($updatedPermissions, $membership->permissionsFor(BusinessRole::Seller));
        $this->assertDatabaseCount('business_invitations', 0);
        $this->assertSame('5000.00', (string) $business->fresh()->sms_wallet_balance);
    }

    public function test_accepting_invitation_creates_membership_with_stored_permissions(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['mobile' => '09123334455']);
        $business = Business::factory()->for($owner, 'owner')->create();

        $permissions = [
            BusinessPermission::InvoiceView->value,
            BusinessPermission::PartyView->value,
            BusinessPermission::PartyCreate->value,
        ];

        $invitation = BusinessInvitation::factory()->create([
            'business_id' => $business->id,
            'mobile' => $invitee->mobile,
            'invited_by' => $owner->id,
            'roles' => [BusinessRole::Seller->value],
            'permissions' => [
                BusinessRole::Seller->value => $permissions,
            ],
        ]);

        $membership = app(AcceptBusinessInvitationAction::class)->handle($invitation, $invitee);

        $this->assertTrue($membership->hasRole(BusinessRole::Seller));
        $this->assertSame($permissions, $membership->permissionsFor(BusinessRole::Seller));
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertSame($business->id, $invitee->fresh()->current_business_id);
    }

    public function test_invite_link_redirects_guest_to_login_and_accepts_after_auth(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['mobile' => '09124445566']);
        $business = Business::factory()->for($owner, 'owner')->create();

        $invitation = BusinessInvitation::factory()->create([
            'business_id' => $business->id,
            'mobile' => $invitee->mobile,
            'invited_by' => $owner->id,
            'roles' => [BusinessRole::Viewer->value],
            'permissions' => [
                BusinessRole::Viewer->value => BusinessRole::Viewer->defaultPermissions(),
            ],
        ]);

        $this->get(route('business-invitations.accept', $invitation->token))
            ->assertRedirect();

        $this->assertTrue(session()->has('business_invitation_token'));

        $this->actingAs($invitee)
            ->get(route('business-invitations.accept', $invitation->token))
            ->assertRedirect(route('user.dashboard'));

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_mismatched_mobile_cannot_accept_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['mobile' => '09125556677']);
        $other = User::factory()->create(['mobile' => '09126667788']);
        $business = Business::factory()->for($owner, 'owner')->create();

        $invitation = BusinessInvitation::factory()->create([
            'business_id' => $business->id,
            'mobile' => $invitee->mobile,
            'invited_by' => $owner->id,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(AcceptBusinessInvitationAction::class)->handle($invitation, $other);
    }
}
