<?php

namespace Tests\Unit;

use App\Exceptions\System\DisallowedArtisanCommandException;
use App\Services\System\SystemCommandRunner;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SystemCommandRunnerTest extends TestCase
{
    public function test_disallowed_command_is_rejected(): void
    {
        $this->expectException(DisallowedArtisanCommandException::class);

        app(SystemCommandRunner::class)->assertAllowed('migrate:fresh');
    }

    public function test_normalize_command_strips_artisan_prefix(): void
    {
        $runner = app(SystemCommandRunner::class);

        $this->assertSame('cache:clear', $runner->normalizeCommand('php artisan cache:clear'));
        $this->assertSame('queue:restart', $runner->normalizeCommand('artisan queue:restart'));
        $this->assertSame('view:clear', $runner->normalizeCommand('  view:clear  '));
    }

    public function test_php_binary_prefers_configured_value(): void
    {
        config(['system-functions.php_binary' => 'C:\\php\\php.exe']);

        $this->assertSame('C:\\php\\php.exe', app(SystemCommandRunner::class)->phpBinary());
    }

    public function test_allowed_command_runs_through_process(): void
    {
        Process::fake([
            '*' => Process::result(output: 'Application cache cleared successfully.'),
        ]);

        $chunks = [];

        $exitCode = app(SystemCommandRunner::class)->run(
            'cache:clear',
            function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
        );

        $this->assertSame(0, $exitCode);
        $this->assertNotEmpty($chunks);
        Process::assertRan(fn ($process) => str_contains($process->command[2] ?? '', 'cache:clear')
            || collect($process->command)->contains('cache:clear'));
    }
}
