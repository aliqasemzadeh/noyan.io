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

class UserBusinessViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_view_shows_clean_layout_without_orientation_or_preview(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create([
            'name' => 'فروشگاه نمونه',
            'type' => BusinessType::Store,
            'category' => BusinessCategory::Other,
            'legal_name' => 'شرکت نمونه',
            'invoice_orientation' => InvoiceOrientation::Landscape,
        ]);
        $user->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($user)
            ->test('pages::panel.user.business.view', ['business' => $business])
            ->assertOk()
            ->assertSee('فروشگاه نمونه')
            ->assertSee(__('general.contact_info'))
            ->assertSee(__('general.invoice_colors'))
            ->assertSee('شرکت نمونه')
            ->assertDontSee(__('general.invoice_orientation'))
            ->assertDontSee(__('general.invoice_orientation_landscape'))
            ->assertDontSee(__('general.invoice_preview'))
            ->assertDontSee(__('general.preview_invoice'))
            ->assertDontSee(__('general.preview_quote'));
    }
}
