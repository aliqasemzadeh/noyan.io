<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class SystemBackupPageTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_backups_page(): void
    {
        $this->get(route('system.backups.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_view_backups_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('system.backups.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_backups_page(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)
            ->get(route('system.backups.index'))
            ->assertOk()
            ->assertSee(__('general.system_backups'))
            ->assertSee(__('general.backup_run_now'))
            ->assertSee(__('general.backup_schedule_hint'));
    }

    public function test_run_backup_uses_database_only_local_options(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', Mockery::on(function (array $options): bool {
                return ($options['--only-db'] ?? false) === true
                    && ($options['--only-to-disk'] ?? null) === 'local'
                    && ($options['--disable-notifications'] ?? false) === true;
            }))
            ->andReturn(0);

        Artisan::shouldReceive('output')->andReturn('Backup completed!');

        $user = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($user)
            ->test('pages::panel.administrator.system-management.backup.index')
            ->call('runBackup')
            ->assertSet('isRunning', false);
    }

    public function test_delete_backup_removes_local_file(): void
    {
        Storage::fake('local');

        config(['backup.backup.name' => 'noyan-test']);

        $path = 'noyan-test/2026-01-01-00-00-00.zip';
        Storage::disk('local')->put($path, 'zip-content');

        $user = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($user)
            ->test('pages::panel.administrator.system-management.backup.index')
            ->call('confirmDelete', $path)
            ->call('deleteBackup')
            ->assertSet('deletingPath', null);

        Storage::disk('local')->assertMissing($path);
    }

    public function test_sidebar_includes_backup_and_settings_links(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)
            ->get(route('system.functions.index'))
            ->assertOk()
            ->assertSee(route('system.backups.index'), false)
            ->assertSee(route('system.settings.index'), false)
            ->assertSee(__('general.system_backups'))
            ->assertSee(__('general.system_settings'));
    }
}
