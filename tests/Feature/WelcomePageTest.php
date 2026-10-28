<?php

namespace Tests\Feature;

use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_welcome_page_uses_general_settings_branding(): void
    {
        Storage::fake('public');

        $logoPath = 'settings/logo.png';
        $faviconPath = 'settings/favicon.ico';
        Storage::disk('public')->put($logoPath, 'logo');
        Storage::disk('public')->put($faviconPath, 'favicon');

        $settings = app(GeneralSettings::class);
        $settings->site_name = 'Noyan Brand';
        $settings->site_short_name = 'Noyan';
        $settings->site_description = 'Smart financial system for businesses';
        $settings->site_logo = $logoPath;
        $settings->site_favicon = $faviconPath;
        $settings->save();

        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertSee('<title>Noyan Brand</title>', false)
            ->assertSee('Smart financial system for businesses', false)
            ->assertSee('Noyan', false)
            ->assertSee(Storage::disk('public')->url($logoPath), false)
            ->assertSee(Storage::disk('public')->url($faviconPath), false)
            ->assertSee('$flux.dark = ! $flux.dark', false);
    }

    public function test_welcome_page_falls_back_when_optional_settings_are_empty(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->site_name = 'Noyan';
        $settings->site_short_name = 'Noyan';
        $settings->site_description = '';
        $settings->site_logo = null;
        $settings->site_favicon = null;
        $settings->save();

        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertSee('<title>Noyan</title>', false)
            ->assertSee(__('general.welcome_subheadline', [], 'en'), false)
            ->assertDontSee('rel="icon"', false);
    }

    public function test_welcome_page_shows_accounting_ai_intro(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertSee(__('general.accounting_ai_title'), false)
            ->assertSee(__('general.accounting_ai_description'), false)
            ->assertSee(__('general.accounting_ai_footer'), false);
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
