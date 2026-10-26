<?php

namespace Tests\Feature;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Enums\System\UpdateMode;
use App\Models\Accounting\GlAccount;
use App\Models\Business;
use App\Models\User;
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
        $this->assertSame('0.1.0-beta.4', AppVersion::current());

        $pending = app(VersionUpdateRegistry::class)->pending();

        $this->assertNotEmpty($pending);
        $this->assertSame('0.1.0-beta.1', $pending[0]::version());
        $this->assertSame('0.1.0-beta.4', $pending[array_key_last($pending)]::version());
    }

    public function test_second_update_skips_already_applied_version_commands(): void
    {
        Process::fake([
            '*' => Process::result(output: 'ok'),
        ]);

        $updater = app(ProjectUpdater::class);

        $this->assertSame(0, $updater->run(UpdateMode::Quick, fn () => null));
        $this->assertSame('0.1.0-beta.4', AppVersion::installed());
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
            ->assertSee('0.1.0-beta.4', false);
    }

    public function test_standard_coa_uses_persian_account_names(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();

        app(SeedBusinessChartOfAccountsAction::class)->handle($business);

        $sales = GlAccount::findBySystemKey($business, 'sales');
        $cash = GlAccount::findBySystemKey($business, 'cash_and_bank');

        $this->assertSame('فروش کالا', $sales?->name);
        $this->assertSame('موجودی نقد و بانک', $cash?->name);
        $this->assertDatabaseHas('gl_accounts', [
            'business_id' => $business->id,
            'full_code' => '1',
            'name' => 'دارایی‌ها',
        ]);
    }
}
