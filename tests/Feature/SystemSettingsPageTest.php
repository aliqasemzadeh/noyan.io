<?php

namespace Tests\Feature;

use App\Models\User;
use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;
use App\Settings\MaintenanceSettings;
use App\Settings\SecuritySettings;
use App\Settings\SocialSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class SystemSettingsPageTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_settings_page(): void
    {
        $this->get(route('system.settings.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_view_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('system.settings.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_settings_page(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)
            ->get(route('system.settings.index'))
            ->assertOk()
            ->assertSee(__('general.system_settings'))
            ->assertSee(__('general.general_settings'))
            ->assertSee(__('general.contact_settings'))
            ->assertSee(__('general.maintenance_settings'))
            ->assertSee(__('general.security_settings'))
            ->assertSee(__('general.social_settings'));
    }

    public function test_administrator_can_save_general_settings(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($user)
            ->test('system-management.setting.general')
            ->set('form.site_name', 'Noyan Site')
            ->set('form.site_short_name', 'Noyan')
            ->set('form.site_description', 'Test description')
            ->set('form.locale', 'fa')
            ->set('form.timezone', 'Asia/Tehran')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(GeneralSettings::class);

        $this->assertSame('Noyan Site', $settings->site_name);
        $this->assertSame('Noyan', $settings->site_short_name);
        $this->assertSame('Test description', $settings->site_description);
    }

    public function test_administrator_can_save_contact_settings(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($user)
            ->test('system-management.setting.contact')
            ->set('form.mobile', '09121234567')
            ->set('form.phone', '02112345678')
            ->set('form.address', 'Tehran')
            ->set('form.support_email', 'support@example.com')
            ->set('form.phone_numbers', ['02187654321'])
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(ContactSettings::class);

        $this->assertSame('09121234567', $settings->mobile);
        $this->assertSame('support@example.com', $settings->support_email);
        $this->assertSame(['02187654321'], $settings->phone_numbers);
    }

    public function test_administrator_can_enable_maintenance_mode(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($user)
            ->test('system-management.setting.maintenance')
            ->set('form.is_maintenance_mode', true)
            ->set('form.secret_token', 'test-secret-token')
            ->set('form.message', 'Under maintenance')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(MaintenanceSettings::class);

        $this->assertTrue($settings->is_maintenance_mode);
        $this->assertSame('test-secret-token', $settings->secret_token);
        $this->assertTrue(app()->isDownForMaintenance());

        Artisan::call('up');
    }

    public function test_administrator_can_save_security_settings(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($user)
            ->test('system-management.setting.security')
            ->set('form.banned_ips', ['1.2.3.4'])
            ->set('form.banned_mobiles', ['09121111111'])
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SecuritySettings::class);

        $this->assertSame(['1.2.3.4'], $settings->banned_ips);
        $this->assertSame(['09121111111'], $settings->banned_mobiles);
    }

    public function test_banned_ip_receives_forbidden_response(): void
    {
        $security = app(SecuritySettings::class);
        $security->banned_ips = ['127.0.0.1'];
        $security->save();

        $this->get(route('login'))
            ->assertForbidden();
    }

    public function test_banned_mobile_cannot_request_otp(): void
    {
        $security = app(SecuritySettings::class);
        $security->banned_mobiles = ['09121234567'];
        $security->save();

        Livewire::test('pages::auth.login')
            ->set('mobile', '09121234567')
            ->call('sendCode')
            ->assertHasErrors(['mobile']);
    }

    public function test_administrator_can_save_social_settings(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($user)
            ->test('system-management.setting.social')
            ->set('form.telegram', 'https://t.me/noyan')
            ->set('form.instagram', 'https://instagram.com/noyan')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SocialSettings::class);

        $this->assertSame('https://t.me/noyan', $settings->telegram);
        $this->assertSame('https://instagram.com/noyan', $settings->instagram);
    }
}
