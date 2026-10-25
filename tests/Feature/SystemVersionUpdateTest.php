<?php

namespace Tests\Feature;

use App\Enums\System\UpdateMode;
use App\Services\System\ProjectUpdater;
use App\Services\System\VersionUpdateRegistry;
use App\Support\System\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SystemVersionUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_releases_include_initial_beta_when_not_installed(): void
    {
        $this->assertSame('0.0.0', AppVersion::installed());
        $this->assertSame('0.1.0-beta.1', AppVersion::current());

        $pending = app(VersionUpdateRegistry::class)->pending();

        $this->assertNotEmpty($pending);
        $this->assertSame('0.1.0-beta.1', $pending[0]::version());
    }

    public function test_second_update_skips_already_applied_version_commands(): void
    {
        Process::fake([
            '*' => Process::result(output: 'ok'),
        ]);

        $updater = app(ProjectUpdater::class);

        $this->assertSame(0, $updater->run(UpdateMode::Quick, fn () => null));
        $this->assertSame('0.1.0-beta.1', AppVersion::installed());

        Process::fake([
            '*' => Process::result(output: 'ok'),
        ]);

        $this->assertSame(0, $updater->run(UpdateMode::Quick, fn () => null));

        Process::assertDidntRun(function ($process): bool {
            return collect($process->command)->contains('accounting:seed-coa');
        });

        Process::assertDidntRun(function ($process): bool {
            return collect($process->command)->contains('accounting:backfill-gl');
        });

        Process::assertRan(function ($process): bool {
            return collect($process->command)->contains('migrate');
        });
    }

    public function test_welcome_page_shows_app_version(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('0.1.0-beta.1', false);
    }
}
