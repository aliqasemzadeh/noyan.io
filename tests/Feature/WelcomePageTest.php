<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_renders_successfully(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertSee(__('general.welcome_headline'), false);
    }

    public function test_welcome_page_shows_login_link_for_guests(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertSee(route('login'), false)
            ->assertSee(__('general.login'), false);
    }

    public function test_welcome_page_shows_dashboard_link_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->withSession(['locale' => 'en'])
            ->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee(route('dashboard'), false)
            ->assertSee(__('general.dashboard'), false);
    }

    public function test_locale_switch_stores_persian_in_session(): void
    {
        $this->withSession(['locale' => 'en'])
            ->from('/')
            ->get(route('locale.switch', 'fa'))
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'fa');

        $this->get('/')
            ->assertOk()
            ->assertSee(__('general.welcome_headline'), false);
    }

    public function test_locale_switch_stores_english_in_session(): void
    {
        $this->withSession(['locale' => 'fa'])
            ->from('/')
            ->get(route('locale.switch', 'en'))
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'en');

        $this->get('/')
            ->assertOk()
            ->assertSee(__('general.welcome_headline', [], 'en'), false);
    }

    public function test_locale_switch_rejects_invalid_locale(): void
    {
        $this->get(route('locale.switch', 'de'))
            ->assertNotFound();
    }
}
