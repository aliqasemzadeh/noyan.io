<?php

namespace App\Services\System;

use App\Exceptions\System\DisallowedArtisanCommandException;
use Closure;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\PhpExecutableFinder;

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

    public function normalizeCommand(string $input): string
    {
        $command = trim($input);
        $command = (string) preg_replace('/^(php\s+)?artisan\s+/i', '', $command);

        return trim($command);
    }

    public function phpBinary(): string
    {
        $configured = config('system-functions.php_binary');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $found = (new PhpExecutableFinder)->find(false);

        if (is_string($found) && $found !== '' && ! $this->isNonCliBinary($found)) {
            return $found;
        }

        if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
            if (! $this->isNonCliBinary(PHP_BINARY)) {
                return PHP_BINARY;
            }

            $sibling = $this->siblingCliBinary(PHP_BINARY);

            if ($sibling !== null) {
                return $sibling;
            }
        }

        return PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
    }

    /**
     * @param  list<string>  $arguments
     * @param  Closure(string): void  $onOutput
     */
    public function artisan(array $arguments, Closure $onOutput, ?string $display = null): int
    {
        $command = [
            $this->phpBinary(),
            base_path('artisan'),
            ...$arguments,
        ];

        return $this->runProcess(
            $command,
            $onOutput,
            $display ?? 'php artisan '.implode(' ', $arguments),
        );
    }

    /**
     * @param  Closure(string): void  $onOutput
     */
    public function run(string $command, Closure $onOutput): int
    {
        $command = $this->normalizeCommand($command);

        $this->assertAllowed($command);

        return $this->artisan([$command], $onOutput, 'php artisan '.$command);
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

    protected function isNonCliBinary(string $binary): bool
    {
        $name = strtolower(basename($binary));

        return str_contains($name, 'cgi')
            || str_contains($name, 'fpm')
            || str_contains($name, 'phpdbg');
    }

    protected function siblingCliBinary(string $binary): ?string
    {
        $directory = dirname($binary);
        $candidate = $directory.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');

        if (is_file($candidate) && ! $this->isNonCliBinary($candidate)) {
            return $candidate;
        }

        return null;
    }
}
