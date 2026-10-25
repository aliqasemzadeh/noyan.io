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
        $this->assertSame([], app(VersionUpdateRegistry::class)->pending());

        $versionCommands = [];

        Process::fake(function (object $process) use (&$versionCommands) {
            $command = collect($process->command);

            foreach (['accounting:seed-coa', 'accounting:backfill-gl', 'migrate'] as $name) {
                if ($command->contains($name)) {
                    $versionCommands[] = $name;
                }
            }

            return Process::result(output: 'ok');
        });

        $this->assertSame(0, $updater->run(UpdateMode::Quick, fn () => null));

        $this->assertNotContains('accounting:seed-coa', $versionCommands);
        $this->assertNotContains('accounting:backfill-gl', $versionCommands);
        $this->assertContains('migrate', $versionCommands);
    }

    public function test_welcome_page_shows_app_version(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('0.1.0-beta.1', false);
    }
}
