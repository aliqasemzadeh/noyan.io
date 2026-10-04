<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class PageTitleLocaleTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_system_dashboard_title_uses_persian_page_and_site_name(): void
    {
        App::setLocale('fa');

        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)
            ->get(route('system.dashboard'))
            ->assertOk()
            ->assertSee('<title>مدیریت سیستم - نویان</title>', false);
    }

    public function test_system_dashboard_title_uses_english_page_and_site_name(): void
    {
        App::setLocale('en');

        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)
            ->get(route('system.dashboard'))
            ->assertOk()
            ->assertSee('<title>System management - Noyan</title>', false);
    }

    public function test_login_title_uses_translated_page_and_site_name(): void
    {
        App::setLocale('fa');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<title>ورود - نویان</title>', false);
    }
}
