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
