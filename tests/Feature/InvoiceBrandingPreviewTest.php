<?php

namespace Tests\Feature;

use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Enums\Business\InvoiceOrientation;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceBrandingPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_invoice_orientation(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'type' => BusinessType::Store,
            'category' => BusinessCategory::Other,
            'invoice_orientation' => InvoiceOrientation::Portrait,
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($user)
            ->test('pages::panel.user.business.edit', ['business' => $business])
            ->set('form.name', $business->name)
            ->set('form.type', BusinessType::Store->value)
            ->set('form.category', BusinessCategory::Other->value)
            ->set('form.invoice_orientation', InvoiceOrientation::Landscape->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('user.businesses.view', $business));

        $this->assertSame(
            InvoiceOrientation::Landscape,
            $business->fresh()->invoiceOrientation(),
        );
    }

    public function test_business_member_can_open_invoice_and_quote_previews(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'invoice_orientation' => InvoiceOrientation::Landscape,
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();

        $this->actingAs($user)
            ->get(route('user.businesses.invoice-preview', $business))
            ->assertOk()
            ->assertSee(__('general.invoice_preview_sample_notice'))
            ->assertSee(__('general.invoice_preview_sample_party'));

        $this->actingAs($user)
            ->get(route('user.businesses.quote-preview', $business))
            ->assertOk()
            ->assertSee(__('general.invoice_preview_sample_notice'))
            ->assertSee(__('general.quote'));
    }

    public function test_stranger_cannot_open_invoice_preview(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner, 'owner')->create();

        $stranger = User::factory()->create();
        $own = Business::factory()->for($stranger, 'owner')->create();
        $stranger->forceFill(['current_business_id' => $own->id])->save();

        $this->actingAs($stranger)
            ->get(route('user.businesses.invoice-preview', $business))
            ->assertForbidden();
    }
}
