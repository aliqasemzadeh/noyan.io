<?php

namespace Tests\Feature;

use App\Enums\System\UpdateMode;
use App\Models\User;
use App\Services\System\ProjectUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class SystemFunctionsPageTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_functions_page(): void
    {
        $this->get(route('system.functions.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_view_functions_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('system.functions.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_functions_page(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        $this->actingAs($user)
            ->get(route('system.functions.index'))
            ->assertOk()
            ->assertSee(__('general.system_functions'))
            ->assertSee(__('general.quick_update'))
            ->assertSee(__('general.full_update'))
            ->assertSee(__('general.command_run'))
            ->assertSee(__('general.cmd_cache_clear'));
    }

    public function test_run_command_rejects_disallowed_artisan_command(): void
    {
        $user = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($user)
            ->test('pages::panel.administrator.system-management.function.index')
            ->call('runCommand', 'migrate:fresh')
            ->assertSet('isRunning', false)
            ->assertSee(__('general.artisan_command_not_allowed', ['command' => 'migrate:fresh']));
    }

    public function test_typed_command_executes_whitelisted_artisan_command(): void
    {
        Process::fake([
            '*' => Process::result(output: 'Application cache cleared successfully.'),
        ]);

        $user = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($user)
            ->test('pages::panel.administrator.system-management.function.index')
            ->set('commandLine', 'php artisan cache:clear')
            ->call('runTypedCommand')
            ->assertSet('isRunning', false)
            ->assertSet('commandLine', '')
            ->assertSee('cache:clear');

        Process::assertRan(function ($process): bool {
            return collect($process->command)->contains('cache:clear');
        });
    }

    public function test_run_command_executes_whitelisted_artisan_command(): void
    {
        Process::fake([
            '*' => Process::result(output: 'Application cache cleared successfully.'),
        ]);

        $user = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($user)
            ->test('pages::panel.administrator.system-management.function.index')
            ->call('runCommand', 'cache:clear')
            ->assertSet('isRunning', false)
            ->assertSee('cache:clear');

        Process::assertRan(function ($process): bool {
            return collect($process->command)->contains('cache:clear');
        });
    }

    public function test_quick_update_skips_composer_and_npm(): void
    {
        Process::fake([
            '*' => Process::result(output: 'ok'),
        ]);

        $exitCode = app(ProjectUpdater::class)->run(
            UpdateMode::Quick,
            function (string $chunk): void {},
        );

        $this->assertSame(0, $exitCode);

        Process::assertRan(fn ($process) => collect($process->command)->contains('git')
            || ($process->command[0] ?? null) === 'git');

        Process::assertDidntRun(function ($process): bool {
            $command = collect($process->command)->implode(' ');

            return str_contains($command, 'composer') || str_contains($command, 'npm');
        });
    }

    public function test_full_update_runs_composer_and_npm(): void
    {
        Process::fake([
            '*' => Process::result(output: 'ok'),
        ]);

        $exitCode = app(ProjectUpdater::class)->run(
            UpdateMode::Full,
            function (string $chunk): void {},
        );

        $this->assertSame(0, $exitCode);

        Process::assertRan(function ($process): bool {
            $command = collect($process->command)->implode(' ');

            return str_contains($command, 'composer');
        });

        Process::assertRan(function ($process): bool {
            $command = collect($process->command)->implode(' ');

            return str_contains($command, 'npm');
        });
    }
}
