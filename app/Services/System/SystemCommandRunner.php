<?php

namespace App\Services\System;

use App\Exceptions\System\DisallowedArtisanCommandException;
use Closure;
use Illuminate\Support\Facades\Process;

class SystemCommandRunner
{
    /**
     * @return array<string, array{label: string, icon: string, color: string}>
     */
    public function allowedCommands(): array
    {
        /** @var array<string, array{label: string, icon: string, color: string}> $commands */
        $commands = config('system-functions.commands', []);

        return $commands;
    }

    public function isAllowed(string $command): bool
    {
        return array_key_exists($command, $this->allowedCommands());
    }

    public function assertAllowed(string $command): void
    {
        if (! $this->isAllowed($command)) {
            throw DisallowedArtisanCommandException::for($command);
        }
    }

    /**
     * @param  Closure(string): void  $onOutput
     */
    public function run(string $command, Closure $onOutput): int
    {
        $this->assertAllowed($command);

        $onOutput('$ php artisan '.$command.PHP_EOL);

        $process = Process::forever()
            ->path(base_path())
            ->start([PHP_BINARY, base_path('artisan'), $command]);

        return $this->drain($process, $onOutput);
    }

    /**
     * @param  Closure(string): void  $onOutput
     * @param  list<string>  $command
     */
    public function runProcess(array $command, Closure $onOutput, ?string $display = null): int
    {
        $onOutput('$ '.($display ?? implode(' ', $command)).PHP_EOL);

        $process = Process::forever()
            ->path(base_path())
            ->start($command);

        return $this->drain($process, $onOutput);
    }

    /**
     * @param  Closure(string): void  $onOutput
     */
    protected function drain(object $process, Closure $onOutput): int
    {
        while ($process->running()) {
            $this->flushLatest($process, $onOutput);
            usleep(50_000);
        }

        $this->flushLatest($process, $onOutput);

        $result = $process->wait();

        $onOutput(PHP_EOL.'Exit code: '.$result->exitCode().PHP_EOL);

        return $result->exitCode() ?? 1;
    }

    /**
     * @param  Closure(string): void  $onOutput
     */
    protected function flushLatest(object $process, Closure $onOutput): void
    {
        $output = $process->latestOutput();
        $error = $process->latestErrorOutput();

        if ($output !== '') {
            $onOutput($output);
        }

        if ($error !== '') {
            $onOutput($error);
        }
    }
}
